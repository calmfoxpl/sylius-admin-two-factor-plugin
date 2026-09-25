<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;

/**
 * A code sent by e-mail as a scheb/2fa provider, for administrators who cannot manage a phone
 * app or a passkey.
 *
 * This is the plugin's own provider rather than scheb/2fa-email on purpose: that one is a single
 * provider for the whole application with one mailer and one form, and a shop that also uses
 * e-mail codes for customers (e.g. calmfox/sylius-shop-two-factor-plugin) already configures it.
 * Sharing it would send administrators the shop's e-mail and form, or the other way round.
 */
final readonly class EmailCodeTwoFactorProvider implements TwoFactorProviderInterface
{
    public const ALIAS = 'admin_email';

    public function __construct(
        private EmailCode $emailCode,
        private TwoFactorFormRendererInterface $formRenderer,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof TwoFactorAdminUserInterface && $user->isEmailCodeEnabled();
    }

    /**
     * Nothing here: the code is sent by the form renderer instead. scheb/2fa prepares a provider
     * only after its form has been rendered, so a code sent here would be announced on the page
     * before it went out, and a mail server failure could never be shown.
     */
    public function prepareAuthentication(object $user): void
    {
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        return $user instanceof TwoFactorAdminUserInterface &&
            $user->isEmailCodeEnabled() &&
            $this->emailCode->verify($user, EmailCode::LOGIN, $authenticationCode);
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
