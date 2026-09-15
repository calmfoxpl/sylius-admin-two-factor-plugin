# Changelog

All notable changes to this project are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added

- Two-factor authentication for administrators with a choice of passkey (WebAuthn) or authenticator app (TOTP).
- Setup page with a passkey first and a QR code for the app; secrets are stored only after a successful first use.
- Second-factor step at login in the admin look, with switching between the paired methods.
- Policy (required, optional, turned off) set in *Configuration → Two-factor authentication*.
- Administrator card with separate *Reset 2FA* and *Turn off 2FA*, a 2FA grid column and `calmfox:admin:2fa:reset`.
- Integration with `calmfox/sylius-admin-invitation-plugin`: invited administrators pair right after setting their password.
- Access to the 2FA pages granted by route name; the policy condition and the admin form renderer apply to administrators only.
- English and Polish translations.
