<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Policy;

use Calmfox\SyliusAdminTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Who has to use two-factor authentication:
 *
 *  - required: every administrator; without a paired method they only reach the setup page,
 *  - optional: whoever pairs a method, plus administrators whose 2FA was reset by another one,
 *  - disabled: nobody; paired methods stay on the accounts but are not asked for.
 */
final class TwoFactorPolicy
{
    public const REQUIRED = 'required';

    public const OPTIONAL = 'optional';

    public const DISABLED = 'disabled';

    public const ALL = [self::REQUIRED, self::OPTIONAL, self::DISABLED];

    private ?string $current = null;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $defaultPolicy,
    ) {
    }

    public function current(): string
    {
        if (null !== $this->current) {
            return $this->current;
        }

        try {
            $settings = $this->settings();
        } catch (\Throwable) {
            // table not migrated yet: fall back to the configured default instead of breaking the login
            return $this->defaultPolicy;
        }

        return $this->current = $settings?->getPolicy() ?? $this->defaultPolicy;
    }

    public function change(string $policy): void
    {
        if (!in_array($policy, self::ALL, true)) {
            throw new \InvalidArgumentException(sprintf('Unknown two-factor policy "%s".', $policy));
        }

        $settings = $this->settings();
        if (null === $settings) {
            $settings = new TwoFactorSettings($policy);
            $this->entityManager->persist($settings);
        } else {
            $settings->setPolicy($policy);
        }

        $this->entityManager->flush();
        $this->current = $policy;
    }

    public function isActive(): bool
    {
        return self::DISABLED !== $this->current();
    }

    /** Must this administrator pair a method before using the panel? */
    public function mustSetUp(TwoFactorAdminUserInterface $adminUser): bool
    {
        if (!$this->isActive() || $adminUser->hasTwoFactorAuthentication()) {
            return false;
        }

        return self::REQUIRED === $this->current() || $adminUser->isTwoFactorSetupRequired();
    }

    private function settings(): ?TwoFactorSettings
    {
        return $this->entityManager->getRepository(TwoFactorSettings::class)->findOneBy([], ['id' => 'ASC']);
    }
}
