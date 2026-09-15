<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\EventListener;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * An administrator who must use two-factor authentication (policy "required", or their 2FA was
 * reset) can only reach the setup page and log out until they pair a passkey or an app.
 */
final readonly class EnforceTwoFactorSetupListener
{
    private const ALLOWED_ROUTES = [
        'calmfox_admin_two_factor_setup',
        'calmfox_admin_two_factor_passkey_registration_options',
        'calmfox_admin_two_factor_passkey_register',
        'sylius_admin_logout',
        'sylius_admin_login',
        'sylius_admin_login_check',
    ];

    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private FirewallMap $firewallMap,
        private UrlGeneratorInterface $urlGenerator,
        private TwoFactorPolicy $policy,
        private string $firewallName,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $route = $request->attributes->get('_route');
        $route = is_string($route) ? $route : '';
        if (in_array($route, self::ALLOWED_ROUTES, true) || str_starts_with($route, '_')) {
            return;
        }

        if ($this->firewallMap->getFirewallConfig($request)?->getName() !== $this->firewallName) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token || $token instanceof TwoFactorTokenInterface) {
            return;
        }

        $adminUser = $token->getUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface || !$this->policy->mustSetUp($adminUser)) {
            return;
        }

        // live components and other background calls just fail; the next page load redirects
        if ($request->isXmlHttpRequest() || 'html' !== $request->getPreferredFormat()) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('calmfox_admin_two_factor_setup')));
    }
}
