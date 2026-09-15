<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Totp;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

/**
 * The authenticator app code form in the admin look — for administrators only. Anyone else
 * (e.g. shop customers handled by another package) gets the renderer this one decorates.
 */
final readonly class AdminTotpFormRenderer implements TwoFactorFormRendererInterface
{
    public function __construct(
        private TwoFactorFormRendererInterface $inner,
        private TokenStorageInterface $tokenStorage,
        private Environment $twig,
        private string $template,
    ) {
    }

    public function renderForm(Request $request, array $templateVars): Response
    {
        if (!$this->tokenStorage->getToken()?->getUser() instanceof TwoFactorAdminUserInterface) {
            return $this->inner->renderForm($request, $templateVars);
        }

        return new Response($this->twig->render($this->template, $templateVars));
    }
}
