<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

/** Calmfox services → Admin two-factor: the panel-wide policy is set once, so it has its own page. */
final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        CalmfoxSection::in($event->getMenu())
            ->addChild('calmfox_two_factor', ['route' => 'calmfox_admin_two_factor_settings'])
            ->setLabel('calmfox_admin_two_factor.settings.title')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }
}
