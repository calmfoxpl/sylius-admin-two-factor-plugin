<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Passkey;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Doctrine\Persistence\ObjectManager;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Passkey as a scheb/2fa provider: after the password, an administrator with a passkey
 * confirms with a fingerprint, face or device PIN. The signed WebAuthn response travels in
 * the regular auth code field as JSON.
 */
final readonly class PasskeyTwoFactorProvider implements TwoFactorProviderInterface
{
    public const ALIAS = 'passkey';

    public function __construct(
        private PasskeyCeremony $ceremony,
        private RequestStack $requestStack,
        private ObjectManager $adminUserManager,
        private TwoFactorFormRendererInterface $formRenderer,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof TwoFactorAdminUserInterface && $user->hasPasskeys();
    }

    public function prepareAuthentication(object $user): void
    {
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        $request = $this->requestStack->getMainRequest();
        if (!$user instanceof TwoFactorAdminUserInterface || null === $request) {
            return false;
        }

        try {
            $decoded = json_decode($authenticationCode, true, 32, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }
        if (!is_array($decoded)) {
            return false;
        }
        /** @var array<string, mixed> $response */
        $response = $decoded;

        try {
            $this->ceremony->verifyLogin($user, $request, $response);
        } catch (PasskeyException) {
            return false;
        }

        $this->adminUserManager->flush();

        return true;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
