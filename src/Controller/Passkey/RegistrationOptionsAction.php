<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller\Passkey;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyCeremony;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/** Challenge for pairing a passkey with the logged-in administrator. */
final readonly class RegistrationOptionsAction
{
    public function __construct(
        private Security $security,
        private PasskeyCeremony $ceremony,
        private JsonCsrfGuard $csrfGuard,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->csrfGuard->check($request);

        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            throw new AccessDeniedException();
        }

        return new JsonResponse(['options' => $this->ceremony->registrationOptions($adminUser, $request)]);
    }
}
