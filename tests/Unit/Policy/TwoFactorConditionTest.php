<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Unit\Policy;

use Calmfox\SyliusAdminTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorCondition;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

final class TwoFactorConditionTest extends TestCase
{
    public function testTurnedOffPolicySkipsTheSecondFactorForAdministrators(): void
    {
        self::assertFalse($this->condition(TwoFactorPolicy::DISABLED)->shouldPerformTwoFactorAuthentication($this->context(new AdminUser())));
        self::assertTrue($this->condition(TwoFactorPolicy::OPTIONAL)->shouldPerformTwoFactorAuthentication($this->context(new AdminUser())));
    }

    public function testOtherUsersAreNotAffected(): void
    {
        self::assertTrue($this->condition(TwoFactorPolicy::DISABLED)->shouldPerformTwoFactorAuthentication($this->context(new ShopUser())));
    }

    private function condition(string $policy): TwoFactorCondition
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new TwoFactorSettings($policy));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new TwoFactorCondition(new TwoFactorPolicy($entityManager, TwoFactorPolicy::REQUIRED));
    }

    private function context(UserInterface $user): AuthenticationContextInterface
    {
        $context = $this->createMock(AuthenticationContextInterface::class);
        $context->method('getUser')->willReturn($user);

        return $context;
    }
}
