# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.2.2] - 2026-10-10

### Fixed

- Behind a reverse proxy that mounts the shop under a prefix (`X-Forwarded-Prefix`, e.g. `example.com/store`), *Reset two-factor* and *Turn off* (grid and edit page) went back to the bare request path, lost the prefix and landed on whatever lives at the root of the domain. The forms now carry the page's full address and the controller accepts it as long as it points at this site.

## [1.2.1] - 2026-10-05

### Fixed

- The policy page answered with an error on Sylius before 2.2.9, whose page title reads a resource name this page does not have.

## [1.2.0] - 2026-10-05

### Changed

- The policy page moved from Configuration to **Calmfox services → Two-factor authentication**, a group of the admin menu that every Calmfox plugin shares for the settings of its service.

## [1.1.0] - 2026-09-25

### Added

- A code sent by e-mail as a third method, for administrators who cannot use a passkey or an authenticator app. It is turned on from the setup page after typing back a first code, and at login it comes after a passkey and the app, which stay one click away.
- E-mail codes are kept in the session as a hash, bound to the administrator, valid for 10 minutes, burnt after 5 wrong tries and limited to one new code a minute. A failing mail server is reported on the page.
- `email` options: `enabled`, `code_lifetime`, `resend_interval`.

### Changed

- `TwoFactorAdminUserInterface` has `isEmailCodeEnabled()`, `setEmailCodeEnabled()` and `clearTwoFactorAuthentication()`, all provided by `TwoFactorAdminUserTrait`. The trait adds the column `email_code_enabled`, so generate and run a migration.
- *Reset 2FA*, *Turn off 2FA* and `calmfox:admin:2fa:reset` remove e-mail codes too; the administrator card and the grid column show them.
- The login page lists all of an administrator's other methods below the current one.
- The setup page's *Log out* / *Not now* link moved from the `form` hook to a new `footer` hook.

## [1.0.0] - 2026-09-15

### Added

- Two-factor authentication for administrators with a choice of passkey (WebAuthn) or authenticator app (TOTP).
- Setup page with a passkey first and a QR code for the app; secrets are stored only after a successful first use.
- Second-factor step at login in the admin look, with switching between the paired methods.
- Policy (required, optional, turned off) set in *Configuration → Two-factor authentication*.
- Administrator card with separate *Reset 2FA* and *Turn off 2FA*, a 2FA grid column and `calmfox:admin:2fa:reset`.
- Integration with `calmfox/sylius-admin-invitation-plugin`: invited administrators pair right after setting their password.
- Access to the 2FA pages granted by route name; the policy condition and the admin form renderer apply to administrators only.
- English and Polish translations.

[Unreleased]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/compare/v1.2.2...HEAD
[1.2.2]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/compare/v1.2.1...v1.2.2
[1.2.1]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/compare/v1.2.0...v1.2.1
[1.2.0]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/compare/v1.1.0...v1.2.0
[1.1.0]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/releases/tag/v1.0.0
