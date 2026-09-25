<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCode;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\Persistence\ObjectManager;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Turns on codes by e-mail from the setup page. The administrator first receives a code and
 * types it back: that proves the mailbox on the account is theirs and working, so a typo in
 * the address or a dead mailbox cannot lock them out at the next login.
 */
final readonly class SetupEmailCodeAction
{
    public const START = 'start';

    public const CONFIRM = 'confirm';

    public function __construct(
        private Security $security,
        private EmailCode $emailCode,
        private ObjectManager $adminUserManager,
        private TwoFactorPolicy $policy,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(Request $request, string $operation): Response
    {
        if (!in_array($operation, [self::START, self::CONFIRM], true)) {
            throw new NotFoundHttpException();
        }

        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::csrfTokenId($operation), $request->request->getString('_csrf_token')))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        $adminUser = $this->security->getUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface) {
            throw new AccessDeniedException();
        }

        // same rule as the setup page itself: it is for administrators without a second factor
        if ($adminUser->hasTwoFactorAuthentication() || !$this->policy->isActive()) {
            return new RedirectResponse($this->urlGenerator->generate('sylius_admin_dashboard'));
        }

        return self::START === $operation ? $this->start($request, $adminUser) : $this->confirm($request, $adminUser);
    }

    public static function csrfTokenId(string $operation): string
    {
        return 'calmfox_admin_two_factor_email_' . $operation;
    }

    private function start(Request $request, TwoFactorAdminUserInterface $adminUser): Response
    {
        $secondsLeft = $this->emailCode->secondsUntilResend();

        return match ($this->emailCode->send($adminUser, EmailCode::SETUP)) {
            EmailCode::SENT => $this->back($request, 'success', ['message' => 'calmfox_admin_two_factor.email.code_sent', 'parameters' => ['%email%' => EmailCode::maskedRecipient($adminUser)]]),
            EmailCode::THROTTLED => $this->back($request, 'info', ['message' => 'calmfox_admin_two_factor.email.wait', 'parameters' => ['%seconds%' => $secondsLeft]]),
            default => $this->back($request, 'error', 'calmfox_admin_two_factor.email.send_failed'),
        };
    }

    private function confirm(Request $request, TwoFactorAdminUserInterface $adminUser): Response
    {
        if (!$this->emailCode->verify($adminUser, EmailCode::SETUP, $request->request->getString('code'))) {
            return $this->back($request, 'error', $this->emailCode->isBurnt($adminUser, EmailCode::SETUP)
                ? 'calmfox_admin_two_factor.email.burnt'
                : 'calmfox_admin_two_factor.email.wrong_code');
        }

        $adminUser->setEmailCodeEnabled(true);
        $adminUser->setTwoFactorSetupRequired(false);
        $this->adminUserManager->flush();

        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('success', 'calmfox_admin_two_factor.setup.email_enabled');
        }

        return new RedirectResponse($this->urlGenerator->generate('sylius_admin_dashboard'));
    }

    /** @param string|array{message: string, parameters: array<string, mixed>} $message */
    private function back(Request $request, string $type, string|array $message): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add($type, $message);
        }

        return new RedirectResponse($this->urlGenerator->generate('calmfox_admin_two_factor_setup') . '#calmfox-two-factor-email');
    }
}
