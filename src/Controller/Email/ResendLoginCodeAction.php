<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCode;
use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCodeTwoFactorProvider;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** "Send a new code" on the login form: the new code replaces the previous one. */
final readonly class ResendLoginCodeAction
{
    public const CSRF_TOKEN_ID = 'calmfox_admin_two_factor_email_resend';

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private EmailCode $emailCode,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $request->request->getString('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $token = $this->tokenStorage->getToken();
        $adminUser = $token?->getUser();
        if (!$token instanceof TwoFactorTokenInterface || !$adminUser instanceof TwoFactorAdminUserInterface || !$adminUser->isEmailCodeEnabled()) {
            throw new AccessDeniedException();
        }

        $secondsLeft = $this->emailCode->secondsUntilResend();
        $result = $this->emailCode->send($adminUser, EmailCode::LOGIN);

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            match ($result) {
                EmailCode::SENT => $session->getFlashBag()->add('success', 'calmfox_admin_two_factor.email.code_resent'),
                EmailCode::THROTTLED => $session->getFlashBag()->add('info', ['message' => 'calmfox_admin_two_factor.email.wait', 'parameters' => ['%seconds%' => $secondsLeft]]),
                EmailCode::FAILED => $session->getFlashBag()->add('error', 'calmfox_admin_two_factor.email.send_failed'),
            };
        }

        return new RedirectResponse($this->urlGenerator->generate('calmfox_admin_two_factor_login', ['preferProvider' => EmailCodeTwoFactorProvider::ALIAS]));
    }
}
