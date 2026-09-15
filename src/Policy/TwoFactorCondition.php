<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Policy;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Condition\TwoFactorConditionInterface;

/** With the panel policy set to "disabled", administrators are not asked for a second factor. Other users are not this condition's business. */
final readonly class TwoFactorCondition implements TwoFactorConditionInterface
{
    public function __construct(private TwoFactorPolicy $policy)
    {
    }

    public function shouldPerformTwoFactorAuthentication(AuthenticationContextInterface $context): bool
    {
        if (!$context->getUser() instanceof TwoFactorAdminUserInterface) {
            return true;
        }

        return $this->policy->isActive();
    }
}
