<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Doctrine\Persistence\ObjectManager;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Another administrator's two-factor authentication, from the grid or the edit page:
 *
 *  - reset: removes the paired app and passkeys and makes them pair again at next login,
 *    whatever the policy — for a lost or replaced phone,
 *  - disable: removes the paired app and passkeys; with the policy "required" they will still
 *    be asked to pair at next login, with "optional" they simply log in with the password.
 */
final readonly class ManageAction
{
    public const RESET = 'reset';

    public const DISABLE = 'disable';

    /** @param UserRepositoryInterface<TwoFactorAdminUserInterface> $adminUserRepository */
    public function __construct(
        private UserRepositoryInterface $adminUserRepository,
        private ObjectManager $adminUserManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, int|string $id, string $operation): Response
    {
        if (!in_array($operation, [self::RESET, self::DISABLE], true)) {
            throw new NotFoundHttpException();
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId($operation, $id), (string) $request->request->get('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminUser = $this->adminUserRepository->find($id);
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            throw new NotFoundHttpException();
        }

        $adminUser->setTotpSecret(null);
        $adminUser->setPasskeyCredentials([]);
        $adminUser->setTwoFactorSetupRequired(self::RESET === $operation);
        $this->adminUserManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', [
                'message' => 'calmfox_admin_two_factor.manage.' . $operation . '_done',
                'parameters' => ['%email%' => $adminUser->getEmail()],
            ]);
        }

        $target = (string) $request->request->get('_redirect');

        return new RedirectResponse(str_starts_with($target, '/') && !str_starts_with($target, '//')
            ? $target
            : $this->urlGenerator->generate('sylius_admin_admin_user_index'));
    }

    public static function csrfTokenId(string $operation, int|string $id): string
    {
        return 'calmfox_admin_two_factor_' . $operation . $id;
    }
}
