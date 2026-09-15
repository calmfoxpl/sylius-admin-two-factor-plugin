<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\DependencyInjection;

use Calmfox\SyliusAdminInvitationPlugin\Event\InvitationAcceptedEvent;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CalmfoxSyliusAdminTwoFactorExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var array{default_policy: string, firewall: string, passkeys: array{enabled: bool, rp_name: string, rp_id: string|null}} $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('calmfox_sylius_admin_two_factor.default_policy', $config['default_policy']);
        $container->setParameter('calmfox_sylius_admin_two_factor.firewall', $config['firewall']);
        $container->setParameter('calmfox_sylius_admin_two_factor.passkeys.enabled', $config['passkeys']['enabled']);
        $container->setParameter('calmfox_sylius_admin_two_factor.passkeys.rp_name', $config['passkeys']['rp_name']);
        $container->setParameter('calmfox_sylius_admin_two_factor.passkeys.rp_id', $config['passkeys']['rp_id']);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        // integration with calmfox/sylius-admin-invitation-plugin, when it is installed
        if (!class_exists(InvitationAcceptedEvent::class)) {
            $container->removeDefinition('calmfox_admin_two_factor.listener.invitation_accepted');
        }

        if (!$config['passkeys']['enabled']) {
            $container->removeDefinition('calmfox_admin_two_factor.passkey.provider');
        }
    }

    /** The settings entity is mapped by the plugin itself, so the application does not have to. */
    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'CalmfoxSyliusAdminTwoFactorPlugin' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => \dirname(__DIR__) . '/Entity',
                        'prefix' => 'Calmfox\SyliusAdminTwoFactorPlugin\Entity',
                        'alias' => 'CalmfoxSyliusAdminTwoFactorPlugin',
                    ],
                ],
            ],
        ]);
    }
}
