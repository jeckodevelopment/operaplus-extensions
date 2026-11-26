#!/usr/bin/env bash
# Only invoked against the isolated CI Joomla database/server.
set -euo pipefail
: "${JOOMLA_ROOT:?}"
root="$(pwd)"
version="$(cat VERSION)"
db() { mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "$1"; }
package="$(db "SELECT extension_id FROM ci_extensions WHERE element='pkg_operaplus' AND type='package'")"
[[ "$package" =~ ^[0-9]+$ ]]
site="$(db "SELECT s.update_site_id FROM ci_update_sites s JOIN ci_update_sites_extensions x ON x.update_site_id=s.update_site_id WHERE x.extension_id=$package AND s.location='https://github.com/jeckodevelopment/operaplus-extensions/releases/latest/download/updates.xml'")"
[[ "$site" =~ ^[0-9]+$ ]]
# Same generated feed, served locally. No network dependencies or production changes.
cp "$root/dist/updates.xml" "$JOOMLA_ROOT/ci-update-feed.xml"
db "UPDATE ci_update_sites SET enabled=0; UPDATE ci_update_sites SET enabled=1,location='http://127.0.0.1:8080/ci-update-feed.xml',last_check_timestamp=0 WHERE update_site_id=$site"
(cd "$JOOMLA_ROOT" && php cli/joomla.php update:extensions:check --no-interaction)
test "$(db "SELECT version FROM ci_updates WHERE extension_id=$package")" = "$version"
db "UPDATE ci_update_sites SET location='https://github.com/jeckodevelopment/operaplus-extensions/releases/latest/download/updates.xml' WHERE update_site_id=$site"
rm "$JOOMLA_ROOT/ci-update-feed.xml"
# Seed an actual module instance with contextual configuration and all-page assignment.
db "INSERT INTO ci_modules (title,position,published,module,access,showtitle,params,client_id,language) VALUES ('CI Atlas contextual','sidebar-right',1,'mod_operaplus_atlas',1,0,'{\"mode\":\"context\",\"field_name\":\"atlas-entities\",\"primary_field\":\"atlas-primary\",\"show_all\":0,\"hide_empty\":1,\"cache\":0}',0,'*'); INSERT INTO ci_modules_menu (moduleid,menuid) VALUES (LAST_INSERT_ID(),0)"
module="$(db "SELECT id FROM ci_modules WHERE title='CI Atlas contextual'")"
[[ "$module" =~ ^[0-9]+$ ]]
article="$(db "SELECT id FROM ci_content WHERE title='CI Atlas field test'")"
[[ "$article" =~ ^[0-9]+$ ]]
before="$(db "SELECT field_id,item_id,value FROM ci_fields_values WHERE item_id='$article' ORDER BY field_id,value")"
test -n "$before"
settings="$(db "SELECT params FROM ci_modules WHERE id=$module")"
(cd "$JOOMLA_ROOT" && php cli/joomla.php extension:install --no-interaction --path="$root/dist/pkg_operaplus-$version.zip")
test "$(db "SELECT extension_id FROM ci_extensions WHERE element='pkg_operaplus' AND type='package'")" = "$package"
test "$(db "SELECT COUNT(*) FROM ci_extensions WHERE package_id=$package AND JSON_UNQUOTE(JSON_EXTRACT(manifest_cache,'$.version'))='$version'")" = 6
test "$(db "SELECT field_id,item_id,value FROM ci_fields_values WHERE item_id='$article' ORDER BY field_id,value")" = "$before"
test "$(db "SELECT params FROM ci_modules WHERE id=$module")" = "$settings"
test "$(db "SELECT COUNT(*) FROM ci_modules_menu WHERE moduleid=$module AND menuid=0")" = 1
test "$(db "SELECT COUNT(*) FROM ci_extensions WHERE enabled=1 AND element IN ('operaplusatlas','operaplus_templates') AND type='plugin'")" = 2
echo "Native update discovery and 0.3.3 -> $version upgrade preserved package, module settings, menu assignment, enabled plugins and article associations."
