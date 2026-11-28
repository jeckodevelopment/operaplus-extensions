# Opera+ Gallery — milestone 2.0.0

This is the first implementation lot, not an editor feature ready for deployment.

- GAL-02 foundation: shared optimizer gallery mode always emits static WebP (including smaller originals and WebP inputs), 1920 px full and 480 px thumbnail, quality 85 by default. Native Media Manager policy unchanged.
- GAL-03 foundation: local per-article and per-gallery random identifiers, atomic metadata, bounded photos, exclusive writes, reorder, alt text, captions and credits. Removed photos remain on disk until a safe retention policy is implemented.
- GAL-01/GAL-02 integration pending: Joomla editor button and authenticated AJAX upload; verify article ACL (including edit own), media ACL and media upload policy, CSRF, actual uploaded-file checks, PHP size limits, per-user limits and article draft flow. Never expose the storage service directly. File and metadata URLs in the Joomla images folder follow ordinary public Media Manager semantics; use private storage or authenticated delivery if unpublished photos must be confidential.
- GAL-03 integration pending: editor modification UX and conflict detection for text-only updates (current storage checks image ordering only).
- GAL-04/GAL-05 pending: article-only shortcode rendering bound to the current article, escaped metadata, responsive grid, accessible keyboard/touch lightbox, conditional assets, credits and captions.
- GAL-06/GAL-07 pending: real Joomla browser/runtime and installer tests, editor guide, preview package and final release approval. Do not publish a stable release or overwrite the existing 1.1.1 release.

## Automated foundation checks

Run `php tests/gallery-store.php` with GD WebP and EXIF. Regression suite `php tests/image-optimizer.php` must also pass. The upload and rendering UI is not present in this lot.
