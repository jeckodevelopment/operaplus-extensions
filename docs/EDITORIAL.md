# Editorial extensions (preview 0.2.0)

## CTA module

Install mod_operaplus_cta, create a site module and select light newsletter, dark newsletter, horizontal or Atlas portrait. Title, plain-text copy, button text and URL, envelope/book/none icon, watermark and optional image are editable independently. The module does not collect addresses or send email. For the light newsletter use the envelope and watermark; for Atlas use the cream variant, an approved portrait and a link to Atlas. Use meaningful alternative text when the image conveys information, and keep its attribution in the editorial context as appropriate. The CTA is styled locally with scoped CSS and supports template overrides. The exact approved magazine layouts and visual validation are separate template work.

## Editorial templates

Install and enable the editors-xtd plugin. In the administrator article editor select Opera+ templates, choose Article, Interview, Review, Longform, Dossier or News, and insert the editable HTML at the selection/caret. Cancel leaves the article unchanged. The plugin uses the Joomla 6 editor button API; TinyMCE toolbar configuration must allow editor-xtd buttons. Native content fields and publication workflow are unchanged. No H1, embedded scripts, external assets or fake author/cast values are inserted. Editorial classes require template styles for their final presentation.

The automated tests exercise URL normalization, variant validation, HTML generation and escaping. Actual installation, the dialog in TinyMCE and responsive/dark-mode visual acceptance require Joomla 6 browser testing. No production deployment or stable release yet.

## Visual acceptance
The standalone Atlas and CTA styles implement the approved warm cream and pink palettes, serif headings, full-width burgundy buttons, envelope watermark and right-hand portrait. Container queries respond to module width rather than viewport alone. The CI fixture renders actual PHP layouts with a synthetic test portrait and captures six browser screenshots (desktop, tablet, mobile; light and dark surroundings). These are test fixtures, not a claim of pixel-perfect visual approval or live Joomla compatibility.
