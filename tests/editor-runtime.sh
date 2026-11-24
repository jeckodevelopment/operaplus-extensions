#!/usr/bin/env bash
set -euo pipefail
: "${JOOMLA_ROOT:?}"
root="$(pwd)"
version="$(cat VERSION)"
(cd "$JOOMLA_ROOT" && php cli/joomla.php extension:install --no-interaction --path="$root/dist/pkg_operaplus-$version.zip")
mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "UPDATE ci_extensions SET enabled=1 WHERE type='plugin' AND folder IN ('fields','editors-xtd') AND element IN ('operaplus_templates','operaplusatlas');"
# Joomla's Uri::base() treats cli-server as CLI; set an explicit site base
# only in the disposable CI installation to avoid /administrator/media URLs.
# Disable first-run onboarding and statistics only in the disposable database.
# Guided tours can redirect the browser away from the page being tested.
mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "UPDATE ci_extensions SET enabled=0 WHERE type='plugin' AND folder='system' AND element IN ('guidedtours','stats');"
php -r '
    $path = $argv[1];
    $config = file_get_contents($path);
    $value = "public " . chr(36) . "live_site = " . var_export("http://127.0.0.1:8080", true) . ";";
    $pattern = "~public\\s+\\x24live_site\\s*=\\s*[^;]*;~";
    $config = preg_replace($pattern, $value, $config, 1, $count);
    if ($count !== 1) { throw new RuntimeException("Missing live_site configuration"); }
    file_put_contents($path, $config);
' "$JOOMLA_ROOT/configuration.php"
# Local ephemeral web server only.
(cd "$JOOMLA_ROOT" && php -S 127.0.0.1:8080 -t "$JOOMLA_ROOT") > "$RUNNER_TEMP/joomla-web.log" 2>&1 &
server=$!
trap 'kill "$server" 2>/dev/null || true; tail -n 80 "$RUNNER_TEMP/joomla-web.log"' EXIT
for i in {1..30}; do
  if curl -fsS http://127.0.0.1:8080/administrator/index.php >/dev/null; then break; fi
  sleep 1
done
# Seed known public fixtures only in the disposable Joomla installation.
cat > "$JOOMLA_ROOT/cache/operaplus-atlas-v1.json" <<'ATLAS_FIXTURE'
{"schema_version":1,"source_revision":"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa","entities":[{"type":"opera","id":"ci-opera","title":"CI opera","subtitle":"CI composer","url":"https://opera.plus/atlas/opere/ci-opera/"},{"type":"composer","id":"ci-composer","title":"CI composer","url":null},{"type":"theatre","id":"ci-theatre","title":"CI theatre","url":"https://opera.plus/atlas/ci/theatre/"}]}
ATLAS_FIXTURE
mysql --protocol=TCP -h 127.0.0.1 -u joomla_test joomla_test <<'SQL'
INSERT INTO ci_fields (context,title,name,label,default_value,type,description,state,params,fieldparams,language,created_time,modified_time,access) VALUES
('com_content.article','CI Atlas entities','atlas-entities','CI Atlas entities','','operaplusatlas','',1,'{"display":0,"show_on":""}','{"multiple":1}','*',NOW(),NOW(),1),
('com_content.article','CI Atlas primary','atlas-primary','CI Atlas primary','','operaplusatlas','',1,'{"display":0,"show_on":""}','{"multiple":0}','*',NOW(),NOW(),1);
SQL
node tests/editor-runtime.mjs
node tests/atlas-field-runtime.mjs

