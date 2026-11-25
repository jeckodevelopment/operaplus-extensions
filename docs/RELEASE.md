# Packaging and release

Version 1.0.0 candidate ships five individual ZIPs and one cumulative package, pkg_operaplus. For new installations prefer the cumulative package: its native manifest installs the library first and records child extensions. Uninstalling the package removes all children; the installer blocks individual child removal. Individual ZIPs remain available for standalone deployments and manual updates.

The package registers one centralized stable update server at GitHub Releases' latest stable asset, updates.xml. Preview releases do not publish that feed and never become latest. The URL will not serve a feed until the first stable release is published. Individual ZIP installs can be updated manually; do not mix package-managed and individually managed upgrades. The generated feed links the exact versioned ZIP and includes its SHA-256 and Joomla 6/PHP 8.3 constraints.

All manifests and VERSION must agree. The tagged-release workflow runs all three reusable CI suites before publishing assets; it verifies the tag matches VERSION and is an ancestor of main. Version 0.x tags create prereleases only. Do not create the stable tag before runtime/editor acceptance and visual review. The acceptance PR does not create a tag or publish a release.

The browser fixtures and Joomla lifecycle tests are automated; installation on the production site remains outside CI. Native TinyMCE insertion and save/reload and web upload are covered by CI. CI checks native Joomla discovery against the generated feed served locally and performs a native upgrade from the pinned 0.3.3 source package, preserving data. The public GitHub Releases feed must still be checked after publication.
