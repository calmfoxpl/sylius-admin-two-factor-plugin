# Sylius Admin Two-Factor Plugin

[![Build](https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/actions/workflows/build.yml/badge.svg)](https://github.com/calmfoxpl/sylius-admin-two-factor-plugin/actions/workflows/build.yml)
[![License: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Two-factor authentication for the Sylius 2 administration panel, built on [scheb/2fa-bundle](https://github.com/scheb/2fa). Each administrator chooses their second factor: a **passkey** (fingerprint, face or device PIN) or an **authenticator app** (TOTP).

## Features

- **Choice at setup:** a passkey is offered first, because it cannot be phished and there is nothing to retype. An authenticator app with a QR code or manual key comes next.
- **At login:** after the password, a passkey takes one gesture and an app takes a 6-digit code. Administrators with both methods can switch between them.
- **Policy in the panel:** *Configuration → Two-factor authentication* has three settings:
  - **required:** administrators without a second factor can only reach the setup page,
  - **optional:** each administrator decides,
  - **turned off:** nobody is asked.
- **Per administrator:** a card on the administrator's edit page lists the paired app and passkeys, with two separate actions:
  - **Reset 2FA:** removes the methods and requires a new pairing at the next login, whatever the policy. For a lost phone.
  - **Turn off 2FA:** removes the methods and leaves the rest to the policy.

  The same is available as `bin/console calmfox:admin:2fa:reset <email> [--disable]`.
- **Safe setup:** a TOTP secret is stored only after a correct code, and a passkey only after its signature is verified.
- **Careful passkeys:**
  - user verification is required,
  - challenges are one-time and bound to the session,
  - origins are checked strictly,
  - only the account's own keys are accepted,
  - only public keys are stored.

  Built on [lbuchs/webauthn](https://github.com/lbuchs/WebAuthn), which has no dependencies.
- **No security configuration beyond the firewall:** the 2FA pages are opened by route name. The policy condition, form renderer and passkey provider apply to administrators only, so the plugin runs next to other scheb/2fa setups, e.g. [calmfox/sylius-shop-two-factor-plugin](https://github.com/calmfoxpl/sylius-shop-two-factor-plugin).
- **Invitations:** with [calmfox/sylius-admin-invitation-plugin](https://github.com/calmfoxpl/sylius-admin-invitation-plugin) installed, a new administrator pairs a second factor right after setting their password.
- **Translations:** English and Polish.

## Screenshots

Setup offers a passkey first and an authenticator app next; at login the administrator confirms with a passkey or a code:

<table>
  <tr>
    <td valign="top" rowspan="2"><img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/setup.png" width="330" alt="Two-factor setup page"></td>
    <td valign="top"><img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/login-passkey.png" width="330" alt="Second factor with a passkey"></td>
  </tr>
  <tr>
    <td valign="top"><img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/login-code.png" width="330" alt="Second factor with an authenticator app code"></td>
  </tr>
</table>

*Administrators* shows who has which method, the edit page gets a card with *Reset 2FA* and *Turn off 2FA*, and the policy has its own page:

<img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/administrators.png" width="800" alt="Administrators grid with the 2FA column">

<img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/administrator-card.png" width="445" alt="Two-factor authentication card on the administrator page">

<img src="https://raw.githubusercontent.com/calmfoxpl/sylius-admin-two-factor-plugin/main/docs/images/policy.png" width="800" alt="Two-factor authentication policy page">

## Requirements

| | Version |
|---|---|
| PHP | 8.2, 8.3, 8.4, 8.5 |
| Sylius | 2.1, 2.2 |
| Browser for passkeys | any current browser, over https (plain http works on `localhost` only) |

## Installation

1. Require the package:

    ```bash
    composer require calmfox/sylius-admin-two-factor-plugin
    ```

2. Register the bundles in `config/bundles.php`:

    ```php
    Scheb\TwoFactorBundle\SchebTwoFactorBundle::class => ['all' => true],
    Calmfox\SyliusAdminTwoFactorPlugin\CalmfoxSyliusAdminTwoFactorPlugin::class => ['all' => true],
    ```

    If Flex added a `scheb/2fa-bundle` recipe, delete the `config/routes/scheb_2fa.yaml` it created.

3. Import the configuration, e.g. in `config/packages/calmfox_sylius_admin_two_factor.yaml`:

    ```yaml
    imports:
        - { resource: '@CalmfoxSyliusAdminTwoFactorPlugin/config/config.yaml' }

    calmfox_sylius_admin_two_factor:
        passkeys:
            rp_name: 'My Shop'   # shown by the device when creating a passkey

    scheb_two_factor:
        totp:
            issuer: 'My Shop'    # shown in the authenticator app
    ```

4. Import the routes, e.g. in `config/routes/calmfox_sylius_admin_two_factor.yaml`:

    ```yaml
    calmfox_sylius_admin_two_factor_admin:
        resource: '@CalmfoxSyliusAdminTwoFactorPlugin/config/routes/admin.yaml'
        prefix: '/%sylius_admin.path_name%'
    ```

5. Make your `AdminUser` entity support two-factor authentication:

    ```php
    use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
    use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserTrait;

    #[ORM\Entity]
    #[ORM\Table(name: 'sylius_admin_user')]
    class AdminUser extends BaseAdminUser implements TwoFactorAdminUserInterface
    {
        use TwoFactorAdminUserTrait;
    }
    ```

6. Enable two-factor authentication on the admin firewall in `config/packages/security.yaml`:

    ```yaml
    security:
        firewalls:
            admin:
                # ...
                two_factor:
                    auth_form_path: calmfox_admin_two_factor_login
                    check_path: calmfox_admin_two_factor_login_check
                    default_target_path: sylius_admin_dashboard
                    enable_csrf: true
    ```

7. Generate and run a migration. It adds the columns `totp_secret`, `passkey_credentials` and `two_factor_setup_required` to `sylius_admin_user`, and the table `calmfox_admin_two_factor_settings`:

    ```bash
    bin/console doctrine:migrations:diff
    bin/console doctrine:migrations:migrate
    ```

## Configuration

All options are optional; these are the defaults:

```yaml
calmfox_sylius_admin_two_factor:
    default_policy: required      # until the policy is set in the panel: required | optional | disabled
    firewall: admin
    passkeys:
        enabled: true
        rp_name: Sylius           # name the device shows when creating a passkey
        rp_id: ~                  # bare domain passkeys are bound to; null = request host. Changing it invalidates paired passkeys.
```

Everything else (trusted devices, code leeway, window) is regular [scheb/2fa-bundle configuration](https://symfony.com/bundles/SchebTwoFactorBundle/current/configuration.html).

## Appearance

The pages reuse the Sylius admin login screen templates, logo included, so an admin theme you already have applies to them. To change them further, work in your application and leave the plugin untouched:

- **Templates:** override them under `templates/bundles/CalmfoxSyliusAdminTwoFactorPlugin/`. To replace only some blocks, extend the original with `{% extends '@!CalmfoxSyliusAdminTwoFactorPlugin/…' %}`.
- **Twig Hooks:**
  - `calmfox_admin_two_factor.login.page.content`: the code at login,
  - `calmfox_admin_two_factor.setup.page.content` (`flashes`, `header`, `passkey`, `steps`, `form`): setup,
  - `calmfox_admin_two_factor.settings.create.*`: the policy page,
  - `calmfox_two_factor` in `sylius_admin.admin_user.update.content.form.sections#right`: the administrator card.
- **CSS:** the setup choices carry `calmfox-two-factor-choice--passkey` and `calmfox-two-factor-choice--totp`.

## Development

Tests run against [Sylius Test Application](https://github.com/Sylius/TestApplication) with MySQL. The passkey tests use a software authenticator that signs with real P-256 keys:

```bash
composer install
(cd vendor/sylius/test-application && yarn install && yarn build)
vendor/bin/console assets:install vendor/sylius/test-application/public
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:schema:create

vendor/bin/ecs check          # coding standard
vendor/bin/phpstan analyse    # static analysis, level max
vendor/bin/phpunit            # unit and functional tests
```

## Security

See [SECURITY.md](SECURITY.md) for how to report a vulnerability.

## License

[MIT](LICENSE)
