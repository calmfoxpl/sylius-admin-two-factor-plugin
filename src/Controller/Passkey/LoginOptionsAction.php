<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller\Passkey;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyCeremony;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Challenge for the second factor, limited to the keys of the administrator who just typed their password. */
final readonly class LoginOptionsAction
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private PasskeyCeremony $ceremony,
        private JsonCsrfGuard $csrfGuard,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->csrfGuard->check($request);

        $token = $this->tokenStorage->getToken();
        $adminUser = $token?->getUser();
        if (!$token instanceof TwoFactorTokenInterface || !$adminUser instanceof TwoFactorAdminUserInterface || !$adminUser->hasPasskeys()) {
            throw new AccessDeniedException();
        }

        return new JsonResponse(['options' => $this->ceremony->loginOptions($adminUser, $request)]);
    }
}
