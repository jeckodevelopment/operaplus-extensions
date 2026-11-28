# Opera+ Gallery — milestone 2.0.0

Storage, editor and frontend renderer implemented. Both gallery plugins are opt-in. No stable release yet.

- GAL-02 foundation: shared optimizer gallery mode always emits static WebP (including smaller originals and WebP inputs), 1920 px full and 480 px thumbnail, quality 85 by default. Native Media Manager policy unchanged.
- GAL-03 foundation: local per-article and per-gallery random identifiers, atomic metadata, bounded photos, exclusive writes, reorder, alt text, captions and credits. Removed photos remain on disk until a safe retention policy is implemented.
- GAL-01/GAL-02: administrator editor button, multipart uploads via com_ajax, existing article and checkout checks, core.edit or owner core.edit.own, core.create on com_media, CSRF, Joomla MediaHelper policy, image MIME and size checks, per-file feedback, 2–4 columns. For new articles explicitly save as unpublished first; no automatic publication or save. Existing photo folders and metadata follow public Media Manager semantics: not confidential. Per-user quotas, orphan cleanup and private media policy remain to be defined.
- GAL-03 integration pending: editor modification UX and conflict detection for text-only updates (current storage checks image ordering only).
- GAL-04/GAL-05: article-only shortcode rendering bound to the current article, escaped metadata, responsive grid, native dialog lightbox with keyboard/touch navigation, fallback links, conditional assets, credits and captions.
- GAL-06/GAL-07 pending: real Joomla browser/runtime and installer tests, editor guide, preview package and final release approval. Do not publish a stable release or overwrite the existing 1.1.1 release.

## Automated foundation checks

Run `php tests/gallery-store.php` with GD WebP and EXIF. Regression suite `php tests/image-optimizer.php` must also pass. Editor upload integration is checked separately in the real Joomla runtime; the frontend renderer has a unit test and guest browser checks.
