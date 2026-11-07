# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.1] - 2026-11-07

### Changed

- The warning comment of a setting (the debug switch's) takes its colour from the shared admin UI kit: the `<p>` is a token root (`class="note hryvinskyi-datalayer-note--warning hui-tokens"`) and its colour is `--hui-warn-ink`. The rule lives in this module's own `css/warning-comment.css`, which the settings page links after the kit's stylesheet; `hryvinskyi/magento2-data-layer-admin-ui` no longer carries it (1.1.1 drops it), so install both at 1.1.1 or the comment loses its colour.

## [1.1.0] - 2026-11-06

### Changed

- The setup wizard's server-side step takes its colours, typeface and corner radii from the shared admin UI kit (`hryvinskyi/magento2-admin-ui`), the kit the setup wizard draws with since `hryvinskyi/magento2-data-layer-admin-ui` 1.1. A theme that restyles the kit's `--hui-*` properties restyles the step too. Two tones the kit has no property for still come from the wizard: `--hdl-setup-error-text` and `--hdl-setup-error-deep`.
- The Send test event button is selected as the kit's `hui-button`; the wizard 1.0 class `hdl-setup-button` is no longer emitted by the SDK's components.
- Requires `hryvinskyi/magento2-admin-ui` ^1.0 and `hryvinskyi/magento2-data-layer-admin-ui` ^1.1. With data layer admin UI 1.0 the step's stylesheet would read properties that page does not declare.

## [1.0.1] - 2026-10-20

### Fixed

- The setup wizard's Region list was too narrow for the endpoint names ("www.google-analytics.co…"); it now uses the long control width.
- The first-party visitor ID field's label broke mid-word on the settings page. The label is now "Keep a first-party visitor ID"; "Used when GA’s cookie is blocked" moved to the field comment and to a wizard hint.
- Delivery status said "Read-only counts for this website" at Default, where it shows all websites; the comment now covers both scopes.
- The step's type check and tests resolved the setup wizard SDK through the old app/code path; they now use the sibling package directory, as in vendor/hryvinskyi.

## [1.0.0] - 2026-10-20

### Added

- First public release of `Hryvinskyi_DataLayerServerSideAdminUi`.
