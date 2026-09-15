<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Twig;

use Calmfox\SyliusAdminTwoFactorPlugin\Controller\ManageAction;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class TwoFactorExtension extends AbstractExtension
{
    public function __construct(private readonly TwoFactorPolicy $policy)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('calmfox_two_factor_policy', fn (): string => $this->policy->current()),
            new TwigFunction('calmfox_two_factor_csrf_id', ManageAction::csrfTokenId(...)),
        ];
    }
}
