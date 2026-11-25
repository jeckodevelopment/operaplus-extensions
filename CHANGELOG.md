# Changelog

## 1.0.0 — 2026-11-25
- Validate the actual 0.3.3 to 1.0.0 native package upgrade and persisted configuration/associations.
- Declare the site client in the package update feed and test native Joomla discovery using the generated feed.
- Test contextual and manual Atlas rendering in the real Joomla guest frontend, access and publication restrictions, primary associations and empty states.
- Require all three CI suites for stable publication; check public update feed and downloadable package checksum after publishing. Approved module design unchanged.

## 0.3.3 — development preview
- Remove visible credit rows for public-domain and CC0 Atlas images; retain complete accessible attribution behind a compact disclosure for CC BY and CC BY-SA.
- Add browser regression checks for each license family, fallback images and entities without a destination page.

## 0.3.2 — development preview
- Preserve visible CTA and Atlas link labels in the Opera+ sidebar; compact responsive typography and accessible button sizing.
- Accept the verified Wikimedia thumbnail host used by the Atlas integration index without relaxing other image/license checks.
- Add regression fixtures for host CSS overrides and thumbnail origin validation.

## 0.3.1 — development preview
- Fix optional empty Atlas associations in article forms while retaining native options validation.
- Add native browser acceptance for package upload, editorial templates and Atlas field persistence.

## 0.3.0 — development preview
- Native cumulative package, centralized stable update feed generation, gated tagged-release workflow.
- Automated Joomla 6 installation, reinstall and removal tests for individual and cumulative packages.

## 0.2.0 — development preview
- Standalone responsive editorial Atlas and CTA cards; warm backgrounds, serif headings, right-side portrait, credits and full-width buttons.
- Explicit primary association, cache lock and failure backoff.
- Browser fixture checks and visual artifacts for editorial modules.
- Five individual ZIPs. Joomla install/upgrade and stable publication remain pending.

## 0.1.0 — development preview
- Initial shared Atlas client, association field and module, CTA and editor templates.
