<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Menu;

use Sylius\Bundle\UiBundle\Menu\Event\MenuBuilderEvent;

final class AdminMenuListener
{
    public function __invoke(MenuBuilderEvent $event): void
    {
        $configuration = $event->getMenu()->getChild('configuration');
        if (null === $configuration) {
            return;
        }

        $configuration
            ->addChild('calmfox_two_factor', ['route' => 'calmfox_admin_two_factor_settings'])
            ->setLabel('calmfox_admin_two_factor.settings.title')
            ->setLabelAttribute('icon', 'tabler:shield-lock')
        ;
    }
}
