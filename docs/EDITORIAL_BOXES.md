# Opera+ Editorial Box — 1.0.0

Status: implementation in progress; draft pull request, no release or manual acceptance yet.

## Scope

- Native article custom-field group “Box editoriali”, assigned to Magazine categories only; repeatable Joomla subform, native editor, four types (Approfondimento, Opinione, Nota della redazione, Contesto), title, body, optional signature, stable 128-bit random ID.
- Editor button inserts a standalone paragraph containing a reference to a selected row, including unsaved rows. No independent article save, no AJAX write and no new database tables. Reordering does not alter the reference.
- Content plugin renders only the current article's authorised fields; removes orphaned and duplicate placements. No list/card/OG output or frontend JS. Shared template CSS provides right float and single-column narrow layout.
- Native ACL, workflow and text filters must remain effective. Output uses semantic aside, escaped text and allowlisted editorial HTML. Formatting does not include embedded media, arbitrary CSS or scripts.
- Install/upgrade must be idempotent; preserve category assignments and existing values. Native field setup, editor bridge, content plugin, package integration and Joomla/browser acceptance remain required before release.

## Acceptance

BOX-T01 install/upgrade, opt-in plugins, no duplicate field definitions; category isolation.
BOX-T02 new unpublished article, insert multiple unsaved boxes, native save/reopen, formatting and signature.
BOX-T03 reorder, delete, duplicate and orphan references; cancel edits and verify persisted values.
BOX-T04 author and editorial manager, field ACL, workflow and revisions; no access outside article.
BOX-T05 desktop/tablet/mobile, float, narrow container, adjacent boxes/gallery and dark style, contrast and keyboard.
BOX-T06 category teasers, related cards, search, metadata and feed: no box payload or raw references.
BOX-T07 XSS and malicious URLs, no frontend JS, no assets on unrelated pages; regression existing editorial tools.

## Future scope

Pull quotes, cast/production fact sheets, reading recommendations and references, historical timelines. Do not implement these in 1.0.0.
