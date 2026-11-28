# Opera+ Extensions

Joomla 6 extensions for the Opera+ magazine. Development milestone 2.0.0 (unreleased): see [roadmap](https://github.com/jeckodevelopment/operaplus-extensions/issues/1). Stable release publication is tracked on the [Releases page](https://github.com/jeckodevelopment/operaplus-extensions/releases).

## 2.0.0 — development

- Opera+ Gallery: storage and editor upload preview, disabled by default; not yet ready for publication until the content renderer is integrated. See [gallery plan](docs/GALLERY.md).

## 1.1.1 — published stable

- Library, Atlas module and article field: [Atlas integration](docs/ATLAS.md).
- Configurable CTA and editorial templates: [Editorial extensions](docs/EDITORIAL.md).

- Menu editorial heading: enable System – Opera+ Editorial Heading in Plugins; edit a site menu item, then Editorial heading. Native Show Page Heading and Page Heading control visibility/title in Magazine RC10+. Optional image, subtitle and height are saved in menu params. No frontend plugin logic.

- Image Optimizer: optional Media Manager upload conversion for JPEG/PNG, WebP quality 90, maximum long edge 1920 px. Native Media Manager displays the outcome and reason for any skipped conversion. [Configuration and limitations](docs/IMAGE_OPTIMIZER.md).

CI builds nine individual ZIP packages and one cumulative package. See [release and updates](docs/RELEASE.md). Use the cumulative package from the Releases page for normal installations. CI artifacts are for development testing.
