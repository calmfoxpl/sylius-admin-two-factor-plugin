<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Controller;

use Calmfox\SyliusAdminTwoFactorPlugin\Form\Type\TwoFactorSettingsType;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Configuration → Two-factor authentication: the only shop-wide 2FA setting is the policy. */
final readonly class SettingsAction
{
    public function __construct(
        private TwoFactorPolicy $policy,
        private FormFactoryInterface $formFactory,
        private UrlGeneratorInterface $urlGenerator,
        private Environment $twig,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $form = $this->formFactory->create(TwoFactorSettingsType::class, ['policy' => $this->policy->current()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $policy */
            $policy = $form->get('policy')->getData();
            $this->policy->change($policy);

            $session = $request->getSession();
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('success', 'calmfox_admin_two_factor.settings.saved');
            }

            return new RedirectResponse($this->urlGenerator->generate('calmfox_admin_two_factor_settings'));
        }

        return new Response($this->twig->render('@CalmfoxSyliusAdminTwoFactorPlugin/admin/settings.html.twig', [
            'form' => $form->createView(),
        ]));
    }
}
