# Opera+ Sitemap — included in Opera+ Extensions 2.1.0

Upgrade using the cumulative package (recommended); the individual ZIP remains available for preview testing. The plugin remains opt-in. For fresh installs, enable System – Opera+ Sitemap, set the canonical HTTPS origin and save. Requires root installation, SEF/rewrite and PHP XMLWriter. No core edits or database schema. A static sitemap file or a server rewrite bypassing Joomla must be configured separately.

- /sitemap.xml: index referencing /sitemap-content.xml and configured existing same-site leaf XML sitemaps (Atlas default). Do not add Osservatorio unless verified. If Atlas is itself an index, remove it from the plugin list and submit separately.
- /sitemap-content.xml: eligible public menus, articles routed via eligible public article/category menu, and menu-backed categories. Other components are opt-in by explicit menu IDs; verify anonymous public access before adding. Exclusions are inherited. No guessed non-SEF routes. Single-language root site scope; multilingual canonical behavior requires separate acceptance.
- Guest ACL, publication dates, inherited category/menu visibility and stored robots directives are checked; route/canonical parity and component-level robots overrides must be tested in Joomla before publication. Request anonymously; authenticated requests return 403. XML is rebuilt before ETag validation on each request, preventing stale ACL and publication data; no persistent server cache. More than 50,000 URLs/50 MiB fails closed; pagination is future work.
- JD Learn requires an explicitly tested public route/provider; no automatic course or lesson export.

## Acceptance (anonymous, production-like staging)
1. Install/enable; GET/HEAD both endpoints, XML and content type, 200; ordinary pages unchanged.
2. Confirm index contains verified leaf sitemap URLs only; check Atlas root element.
3. Compare article/menu/category URLs with actual guest canonical and HTTP 200, including nested categories and aliases.
4. Restricted ACL, restricted ancestors, unpublished, future, expired, archived and noindex fixtures must not appear; test menu and category exclusion settings.
5. Create/update/unpublish article; XML and ETag change; unchanged request with If-None-Match gets 304.
6. Verify no login, registration, search, restricted course or admin links; verify manual extra menu IDs individually.
7. Disable plugin and verify normal site; test reinstall.
8. Only after acceptance add Sitemap: https://opera.plus/sitemap.xml to robots.txt and submit verified sitemaps in Search Console.

The native cumulative package and update feed include the plugin from 2.1.0. Production 1.0.0 standalone installation and URL coverage were confirmed by the site operator on 2026-11-28; robots.txt updated and sitemap submitted to Search Console. Full upgrade acceptance remains subject to CI and production verification. Remaining limitations: no standalone category routes without menu, no arbitrary custom-component providers, multilingual routing not yet certified, no sitemap sharding.
