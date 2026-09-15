<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin;

use Calmfox\SyliusAdminTwoFactorPlugin\DependencyInjection\Compiler\RegisterTwoFactorConditionPass;
use Sylius\Bundle\CoreBundle\Application\SyliusPluginTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class CalmfoxSyliusAdminTwoFactorPlugin extends Bundle
{
    use SyliusPluginTrait;

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RegisterTwoFactorConditionPass());
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
