<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller\Passkey;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyCeremony;
use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyException;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Completes pairing: verifies what the device signed and stores the public key on the account. */
final readonly class RegisterAction
{
    public function __construct(
        private Security $security,
        private PasskeyCeremony $ceremony,
        private JsonCsrfGuard $csrfGuard,
        private ObjectManager $adminUserManager,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $this->csrfGuard->check($request);

        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            throw new AccessDeniedException();
        }

        $payload = $request->toArray();
        $name = $payload['name'] ?? null;
        if (!is_array($payload['credential'] ?? null)) {
            return $this->error(PasskeyException::rejected());
        }
        /** @var array<string, mixed> $credential */
        $credential = $payload['credential'];

        try {
            $this->ceremony->register($adminUser, $request, $credential, is_string($name) ? $name : '');
        } catch (PasskeyException $exception) {
            return $this->error($exception);
        }

        $adminUser->setTwoFactorSetupRequired(false);
        $this->adminUserManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'calmfox_admin_two_factor.setup.passkey_added');
        }

        return new JsonResponse(['redirect' => $this->urlGenerator->generate('sylius_admin_dashboard')]);
    }

    private function error(PasskeyException $exception): JsonResponse
    {
        return new JsonResponse(
            ['error' => $this->translator->trans($exception->getMessage(), [], 'validators')],
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }
}
