<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\EventListener;

use Calmfox\SyliusAdminInvitationPlugin\Event\InvitationAcceptedEvent;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/** With the policy "required", a freshly invited administrator pairs a second factor right after the password. */
final readonly class InvitationAcceptedListener
{
    public function __construct(private UrlGeneratorInterface $urlGenerator, private TwoFactorPolicy $policy)
    {
    }

    public function __invoke(InvitationAcceptedEvent $event): void
    {
        $adminUser = $event->getAdminUser();
        if (!$adminUser instanceof TwoFactorAdminUserInterface || !$this->policy->mustSetUp($adminUser)) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('calmfox_admin_two_factor_setup')));
    }
}
