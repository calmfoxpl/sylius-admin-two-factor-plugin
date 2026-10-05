<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\DependencyInjection;

use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('calmfox_sylius_admin_two_factor');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('default_policy')
                    ->info('Policy until someone sets it in the panel (Calmfox services → Two-factor authentication).')
                    ->values(TwoFactorPolicy::ALL)
                    ->defaultValue(TwoFactorPolicy::REQUIRED)
                ->end()
                ->arrayNode('passkeys')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Offer passkeys (fingerprint, face, device PIN) next to the authenticator app.')
                            ->defaultTrue()
                        ->end()
                        ->scalarNode('rp_name')
                            ->info('Name shown by the device when creating the passkey.')
                            ->defaultValue('Sylius')
                            ->cannotBeEmpty()
                        ->end()
                        ->scalarNode('rp_id')
                            ->info('Bare domain passkeys are bound to (e.g. shop.example.com). Null = host of the request. Changing it invalidates all paired passkeys.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('email')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->info('Offer a code sent by e-mail, for administrators who cannot use a passkey or an authenticator app.')
                            ->defaultTrue()
                        ->end()
                        ->integerNode('code_lifetime')
                            ->info('Seconds a code sent by e-mail stays valid.')
                            ->defaultValue(600)
                            ->min(60)
                        ->end()
                        ->integerNode('resend_interval')
                            ->info('Seconds an administrator has to wait before asking for another code.')
                            ->defaultValue(60)
                            ->min(0)
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('firewall')
                    ->info('Name of the admin firewall.')
                    ->defaultValue('admin')
                    ->cannotBeEmpty()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }
}
