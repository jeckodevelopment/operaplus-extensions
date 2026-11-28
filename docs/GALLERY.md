# Opera+ Gallery — milestone 2.0.0

Storage, editor and frontend renderer implemented. Both gallery plugins are opt-in. Stable release 2.0.1 published; all nine acceptance scenarios reported OK by the site owner on 2026-11-28. No implementation work remains in the approved gallery scope. See acceptance evidence and maintenance qualification below.

- GAL-02 foundation: shared optimizer gallery mode always emits static WebP (including smaller originals and WebP inputs), 1920 px full and 480 px thumbnail, quality 85 by default. Native Media Manager policy unchanged.
- GAL-03 foundation: local per-article and per-gallery random identifiers, atomic metadata, bounded photos, exclusive writes, reorder, alt text, captions and credits. Omitted photos are removed only from the private editing copy when committed; previous revisions are kept.
- GAL-01/GAL-02: administrator editor button, multipart uploads via com_ajax, existing article and checkout checks, core.edit or owner core.edit.own, core.create on com_media, CSRF, Joomla MediaHelper policy, image MIME and size checks, per-file feedback, 2–4 columns. For new articles explicitly save as unpublished first; no automatic publication or save. Existing photo folders and metadata follow public Media Manager semantics: not confidential. Session limits: 12 active galleries, 60 photographs per gallery, bounded conversion. Storage monitoring and confidential media are site policies.
- GAL-03: choose an existing gallery from the editor dialog to edit a separate revision; old gallery remains unchanged until native article save. Staged writes are restricted to the originating authenticated session (24-hour window, max 12 active stages); explicit cancellation discards the stage. Unfinished galleries have a persisted expiration marker; they cannot render or be forked. The maintenance CLI reports expired stages and can purge only those, never committed or legacy galleries.
- GAL-04/GAL-05: article-only shortcode rendering bound to the current article, escaped metadata, responsive grid, native dialog lightbox with keyboard/touch navigation, fallback links, conditional assets, credits and captions.
- GAL-06/GAL-07: native Joomla browser/runtime, installer and storage tests, editor guide, maintenance CLI and manual acceptance checklist. Release publication authorized on 2026-11-28 for backend update testing; manual acceptance completed on 2026-11-28 (9/9 reported OK). Native runtime regression exercises upgrading from a stale namespace map; the package refreshes the map through Joomla's native installer API. Provider bootstrap uses its own installed files without changing Joomla core.

## Guida per la redazione

1. In Estensioni > Plugin, abilitare Content - Galleria fotografica Opera+ e Galleria fotografica Opera+ (editor-xtd), dopo il collaudo del pacchetto.
2. Per un articolo nuovo, salvarlo come Non pubblicato. Aprire il menu Contenuto CMS dell'editor e selezionare Galleria fotografica Opera+.
3. Trascinare o selezionare JPG, PNG e WebP. Verificare l'esito per ogni file; ritentare solo i file falliti. Massimo 60 foto, 20 MiB ciascuna o il limite Media/PHP inferiore (mostrato nel popup, con margine per il POST multipart); massimo 12 megapixel per sorgente. Nomi ordinari con lettere, cifre, spazi, trattini e underscore.
4. Riordinare con drag and drop o pulsanti, inserire alt, didascalie e crediti; scegliere 2–4 colonne e premere Inserisci galleria.
5. Per modificare una galleria già inserita, riaprire il pulsante e scegliere la galleria esistente. Le modifiche creano una nuova revisione; applicare e salvare l'articolo.
6. Le anteprime pubbliche sono ritagliate visivamente in riquadri uniformi 4:3; ogni riga è centrata. Il lightbox conserva la fotografia intera. Verificare anteprime, lightbox, frecce, ESC, swipe, desktop e smartphone. Senza JavaScript, i link aprono la foto grande.

Le immagini sono file pubblici standard Joomla: non caricare materiale confidenziale o soggetto a embargo. Le versioni storiche devono essere conservate finché non esiste un inventario affidabile delle referenze. Non eliminare cartelle a mano senza backup.

## Manutenzione (amministratore)

Dalla root di Joomla, con accesso shell:

```sh
php libraries/operaplus/cli/gallery-audit.php
```

Il comando è solo di lettura e restituisce JSON Lines con articolo, galleria, stato, numero foto e dimensione, più un riepilogo su stderr. `retained` indica una revisione già inserita o preesistente, non un orfano accertato. `review` richiede verifica manuale.

Per eliminare soltanto le bozze temporanee scadute da almeno altre 24 ore, fare un backup e arrestare l'accesso HTTP/worker al sito (finestra offline reale; l'opzione non arresta Joomla). Eseguire:

```sh
php libraries/operaplus/cli/gallery-audit.php --purge-expired-stages --offline
```

Il comando ricontrolla lo stato sotto lock prima di eliminare. Non elimina revisioni inserite, file inattesi o directory simboliche. Non esegue pulizia automatica schedulata. Le revisioni inserite ma non salvate nell'articolo e le gallerie antecedenti al marcatore persistente sono conservate intenzionalmente.

## Esiti collaudo manuale

- 2026-11-28: GAL-T01 e GAL-T02 superati. Feedback: eliminare lo sfondo grigio delle anteprime, visualizzare il limite di peso per singola fotografia. Correzioni verificate con esito OK sulla 2.0.0.
- 2026-11-28: GAL-T03 e GAL-T04 superati. Feedback: anteprime con altezza uniforme e gallerie centrate orizzontalmente. Correzione 2.0.1 verificata con esito OK: riquadri 4:3, ritaglio visivo centrato (senza modificare i file), righe incomplete centrate.

- 2026-11-28: GAL-T05 e GAL-T06 superati sulla 2.0.1. Anteprime uniformi e centratura delle gallerie OK; nessuna anomalia segnalata.

- 2026-11-28: GAL-T07, GAL-T08 e GAL-T09 riportati OK sulla 2.0.1. Checklist accessibilità e prestazioni 8/8, nessuna anomalia segnalata.

**Esito finale: 9/9 scenari accettati (100%).** La verifica manuale GAL-T08 era limitata all'audit non distruttivo: la cancellazione offline su fixture scaduta non è stata richiesta in questo lotto; la logica di scadenza e purge è coperta dai test automatici dello store. Non si attesta una cancellazione manuale effettiva sul sito. Nessun intervento distruttivo necessario per l'uso ordinario. Gli esiti sono quelli dichiarati dal collaudatore, non misurazioni indipendenti.

## Collaudo manuale finale (staging Opera+)

- GAL-T01: installazione cumulativa e upgrade da 1.1.1; attivare entrambi i plugin; impostazioni precedenti intatte.
- GAL-T02: articolo nuovo, pulsante e richiesta salvataggio non pubblicato; riprendere senza perdita del testo.
- GAL-T03: drag and drop multiplo JPG/PNG/WebP, file invalido e troppo grande, esiti per foto; controllare cartella, WebP, dimensioni, orientamento, trasparenza.
- GAL-T04: inserire tra due paragrafi, due gallerie distinte, riordinare con mouse e tastiera, alt/didascalie/crediti, colonne 2/3/4; salvare e riaprire.
- GAL-T05: modificare una galleria pubblicata, aggiungere/rimuovere foto e annullare; verificare che l'originale non cambi fino al salvataggio dell'articolo; ripristinare una versione precedente.
- GAL-T06: frontend Opera+ Magazine desktop/tablet/mobile, lightbox, swipe, frecce, ESC, focus, link senza JS; nessun asset galleria sulle altre pagine.
- GAL-T07: utente redattore reale con modifica propria e Media create; negare articolo altrui, checkout, upload senza ACL e CSRF; verifica policy estensioni/MIME.
- GAL-T08: audit CLI in staging, report non distruttivo, purge offline su fixture scaduta; verificare revisioni inserite preservate.
- GAL-T09: accessibilità (lettore di schermo, tastiera, contrasto) e prestazioni con 20–60 immagini; nessuna regressione Media Manager e Atlas.

## Automated foundation checks

Run `php tests/gallery-store.php` with GD WebP and EXIF. Regression suite `php tests/image-optimizer.php` must also pass. Editor upload integration is checked separately in the real Joomla runtime; the frontend renderer has a unit test and guest browser checks.
