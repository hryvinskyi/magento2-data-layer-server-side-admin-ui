# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-10-20

### Fixed

- The setup wizard's Region list was too narrow for the endpoint names ("www.google-analytics.co…"); it now uses the long control width.
- The first-party visitor ID field's label broke mid-word on the settings page. The label is now "Keep a first-party visitor ID"; "Used when GA’s cookie is blocked" moved to the field comment and to a wizard hint.
- Delivery status said "Read-only counts for this website" at Default, where it shows all websites; the comment now covers both scopes.
- The step's type check and tests resolved the setup wizard SDK through the old app/code path; they now use the sibling package directory, as in vendor/hryvinskyi.

## [1.0.0] - 2026-10-20

### Added

- First public release of `Hryvinskyi_DataLayerServerSideAdminUi`.
