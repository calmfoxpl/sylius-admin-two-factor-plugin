<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * The e-mail code form. Showing it sends a code when none is waiting yet, so the page can say
 * truthfully whether the e-mail went out. Reloading the page, or switching to another method
 * and back, reuses the code already sent; after too many wrong guesses a new one is sent only
 * on request, from the form's own button.
 */
final readonly class EmailCodeFormRenderer implements TwoFactorFormRendererInterface
{
    public function __construct(
        private TwoFactorFormRendererInterface $inner,
        private TokenStorageInterface $tokenStorage,
        private EmailCode $emailCode,
    ) {
    }

    public function renderForm(Request $request, array $templateVars): Response
    {
        $adminUser = $this->tokenStorage->getToken()?->getUser();
        if ($adminUser instanceof TwoFactorAdminUserInterface) {
            $sendFailed = false;
            if (!$this->emailCode->isPending($adminUser, EmailCode::LOGIN) && !$this->emailCode->isBurnt($adminUser, EmailCode::LOGIN)) {
                $sendFailed = EmailCode::FAILED === $this->emailCode->send($adminUser, EmailCode::LOGIN);
            }

            $templateVars['emailCode'] = [
                'recipient' => EmailCode::maskedRecipient($adminUser),
                'validMinutes' => $this->emailCode->lifetimeInMinutes(),
                'pending' => $this->emailCode->isPending($adminUser, EmailCode::LOGIN),
                'burnt' => $this->emailCode->isBurnt($adminUser, EmailCode::LOGIN),
                'sendFailed' => $sendFailed,
            ];
        }

        return $this->inner->renderForm($request, $templateVars);
    }
}
