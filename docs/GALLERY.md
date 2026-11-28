# Opera+ Gallery — milestone 2.0.0

Storage, editor and frontend renderer implemented. Both gallery plugins are opt-in. No stable release yet.

- GAL-02 foundation: shared optimizer gallery mode always emits static WebP (including smaller originals and WebP inputs), 1920 px full and 480 px thumbnail, quality 85 by default. Native Media Manager policy unchanged.
- GAL-03 foundation: local per-article and per-gallery random identifiers, atomic metadata, bounded photos, exclusive writes, reorder, alt text, captions and credits. Removed photos remain on disk until a safe retention policy is implemented.
- GAL-01/GAL-02: administrator editor button, multipart uploads via com_ajax, existing article and checkout checks, core.edit or owner core.edit.own, core.create on com_media, CSRF, Joomla MediaHelper policy, image MIME and size checks, per-file feedback, 2–4 columns. For new articles explicitly save as unpublished first; no automatic publication or save. Existing photo folders and metadata follow public Media Manager semantics: not confidential. Per-user quotas, orphan cleanup and private media policy remain to be defined.
- GAL-03: choose an existing gallery from the editor dialog to edit a separate revision; old gallery remains unchanged until native article save. Staged writes are restricted to the originating authenticated session (24-hour window, max 12 active stages); explicit cancellation discards the stage. Abandoned browser sessions and old published revisions are deliberately retained, pending a safe administrator cleanup workflow.
- GAL-04/GAL-05: article-only shortcode rendering bound to the current article, escaped metadata, responsive grid, native dialog lightbox with keyboard/touch navigation, fallback links, conditional assets, credits and captions.
- GAL-06/GAL-07 pending: real Joomla browser/runtime and installer tests, editor guide, preview package and final release approval. Do not publish a stable release or overwrite the existing 1.1.1 release.

## Guida per la redazione

1. In Estensioni > Plugin, abilitare Content - Galleria fotografica Opera+ e Galleria fotografica Opera+ (editor-xtd), dopo il collaudo del pacchetto.
2. Per un articolo nuovo, salvarlo come Non pubblicato. Aprire il menu Contenuto CMS dell'editor e selezionare Galleria fotografica Opera+.
3. Trascinare o selezionare JPG, PNG e WebP. Verificare l'esito per ogni file; ritentare solo i file falliti. Massimo 60 foto, 20 MiB ciascuna o il limite Media/PHP inferiore; massimo 12 megapixel per sorgente. Nomi ordinari con lettere, cifre, spazi, trattini e underscore.
4. Riordinare con drag and drop o pulsanti, inserire alt, didascalie e crediti; scegliere 2–4 colonne e premere Inserisci galleria.
5. Per modificare una galleria già inserita, riaprire il pulsante e scegliere la galleria esistente. Le modifiche creano una nuova revisione; applicare e salvare l'articolo.
6. Verificare anteprime, lightbox, frecce, ESC, swipe, desktop e smartphone. Senza JavaScript, i link aprono la foto grande.

Le immagini sono file pubblici standard Joomla: non caricare materiale confidenziale o soggetto a embargo. Le versioni storiche devono essere conservate finché non esiste un inventario affidabile delle referenze. Non eliminare cartelle a mano senza backup.

## Automated foundation checks

Run `php tests/gallery-store.php` with GD WebP and EXIF. Regression suite `php tests/image-optimizer.php` must also pass. Editor upload integration is checked separately in the real Joomla runtime; the frontend renderer has a unit test and guest browser checks.
