#!/usr/bin/env bash
# Only for the disposable CI installation, never a production site.
set -euo pipefail
: "${JOOMLA_ROOT:?}"
test -f "$JOOMLA_ROOT/configuration.php"
# Disable first-run onboarding and statistics only in the disposable database.
# Guided tours can redirect the browser away from the page being tested.
mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "UPDATE ci_extensions SET enabled=0 WHERE type='plugin' AND folder='system' AND element IN ('guidedtours','stats');"
php -r '
    $path = $argv[1];
    $config = file_get_contents($path);
    $value = "public " . chr(36) . "live_site = " . var_export("http://127.0.0.1:8080", true) . ";";
    $config = preg_replace("~public\\s+\\x24live_site\\s*=\\s*[^;]*;~", $value, $config, 1, $count);
    if ($count !== 1) { throw new RuntimeException("Missing live_site configuration"); }
    file_put_contents($path, $config);
' "$JOOMLA_ROOT/configuration.php"
(cd "$JOOMLA_ROOT" && exec php -S 127.0.0.1:8080 -t "$JOOMLA_ROOT") > "$RUNNER_TEMP/joomla-upload.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true; tail -n 100 "$RUNNER_TEMP/joomla-upload.log"' EXIT
for i in {1..30}; do
  if curl -fsS http://127.0.0.1:8080/administrator/index.php >/dev/null; then break; fi
  sleep 1
done
node tests/web-package-upload.mjs
mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "SELECT element, type FROM ci_extensions WHERE package_id=(SELECT extension_id FROM ci_extensions WHERE element='pkg_operaplus' AND type='package') ORDER BY element;"
count="$(mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "SELECT COUNT(*) FROM ci_extensions WHERE package_id=(SELECT extension_id FROM ci_extensions WHERE element='pkg_operaplus' AND type='package');")"
test "$count" = 11
echo "Native web installer registered all eleven package children."
