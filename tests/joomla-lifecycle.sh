#!/usr/bin/env bash
# Ephemeral CI database only. Never run against a live Joomla instance.
set -euo pipefail
: "${JOOMLA_ROOT:?Set JOOMLA_ROOT to the isolated test installation}"
test -f "$JOOMLA_ROOT/configuration.php"
packages=(lib_operaplus plg_fields_operaplusatlas mod_operaplus_atlas mod_operaplus_cta plg_editorsxtd_operaplus_templates plg_system_operaplus_masthead plg_mediaaction_operaplus_imageoptimizer plg_system_operaplus_imagefeedback plg_editorsxtd_operaplus_gallery)
elements=(operaplus operaplusatlas mod_operaplus_atlas mod_operaplus_cta operaplus_templates operaplus_masthead operaplus_imageoptimizer operaplus_imagefeedback operaplus_gallery)
types=(library plugin module module plugin plugin plugin plugin plugin)
groups=("" fields "" "" editors-xtd system media-action system editors-xtd)
root="$(pwd)"
db() { mysql --protocol=TCP -h 127.0.0.1 -u joomla_test -N -B joomla_test -e "$1"; }
id_for() { db "SELECT extension_id FROM ci_extensions WHERE element='${elements[$1]}' AND type='${types[$1]}' AND folder='${groups[$1]}'"; }
install_one() {
  local i="$1"
  local zips=("$root/dist/${packages[$i]}"-*.zip)
  test "${#zips[@]}" -eq 1
  test -f "${zips[0]}"
  (cd "$JOOMLA_ROOT" && php cli/joomla.php extension:install --no-interaction --path="${zips[0]}")
}
for i in "${!packages[@]}"; do
  install_one "$i"
  id="$(id_for "$i")"
  [[ "$id" =~ ^[0-9]+$ ]]
  echo "Installed ${packages[$i]} as extension $id"
done
test -f "$JOOMLA_ROOT/libraries/operaplus/src/Index.php"
test -f "$JOOMLA_ROOT/libraries/operaplus/src/GalleryStore.php"
test -f "$JOOMLA_ROOT/plugins/editors-xtd/operaplus_gallery/services/provider.php"
test -f "$JOOMLA_ROOT/media/plg_editors-xtd_operaplus_gallery/js/gallery.js"
test -f "$JOOMLA_ROOT/modules/mod_operaplus_atlas/mod_operaplus_atlas.php"
test -f "$JOOMLA_ROOT/modules/mod_operaplus_cta/mod_operaplus_cta.php"
test -f "$JOOMLA_ROOT/plugins/fields/operaplusatlas/services/provider.php"
test -f "$JOOMLA_ROOT/plugins/editors-xtd/operaplus_templates/services/provider.php"
test -f "$JOOMLA_ROOT/plugins/system/operaplus_masthead/services/provider.php"
test -f "$JOOMLA_ROOT/plugins/media-action/operaplus_imageoptimizer/services/provider.php"
test -f "$JOOMLA_ROOT/plugins/system/operaplus_imagefeedback/services/provider.php"
test -f "$JOOMLA_ROOT/media/plg_system_operaplus_imagefeedback/js/feedback.js"
test "$(db "SELECT enabled FROM ci_extensions WHERE element='operaplus_imagefeedback' AND folder='system'")" = 1
test -f "$JOOMLA_ROOT/media/mod_operaplus_cta/css/cta.css"
test -f "$JOOMLA_ROOT/media/plg_editors-xtd_operaplus_templates/js/templates.js"
for i in "${!packages[@]}"; do
  before="$(id_for "$i")"
  install_one "$i"
  after="$(id_for "$i")"
  [[ "$after" =~ ^[0-9]+$ ]]
  # The native library installer replaces the registry entry on reinstall.
  # Other extension types must retain their existing ID.
  if [[ "${types[$i]}" != library ]]; then test "$after" = "$before"; fi
  echo "Reinstalled ${packages[$i]}: $before -> $after"
done
for ((i=${#packages[@]}-1;i>=0;i--)); do
  id="$(id_for "$i")"
  (cd "$JOOMLA_ROOT" && php cli/joomla.php extension:remove "$id" --no-interaction)
  test -z "$(id_for "$i")"
done
echo "All nine extensions installed, reinstalled and removed using Joomla's native installer."

# Test the cumulative package separately from the individual extensions.
version="$(cat VERSION)"
bundle="$root/dist/pkg_operaplus-$version.zip"
(cd "$JOOMLA_ROOT" && php cli/joomla.php extension:install --no-interaction --path="$bundle")
pkg_id="$(db "SELECT extension_id FROM ci_extensions WHERE element='pkg_operaplus' AND type='package'")"
[[ "$pkg_id" =~ ^[0-9]+$ ]]
for i in "${!packages[@]}"; do
  id="$(id_for "$i")"
  [[ "$id" =~ ^[0-9]+$ ]]
  test "$(db "SELECT package_id FROM ci_extensions WHERE extension_id=$id")" = "$pkg_id"
done
test -f "$JOOMLA_ROOT/media/mod_operaplus_atlas/css/atlas.css"
(cd "$JOOMLA_ROOT" && php cli/joomla.php extension:remove "$pkg_id" --no-interaction)
for i in "${!packages[@]}"; do test -z "$(id_for "$i")"; done
echo "Cumulative package installed with all nine children and removed cleanly."
