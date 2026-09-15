<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Unit\Model;

use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyTwoFactorProvider;
use PHPUnit\Framework\TestCase;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

final class TwoFactorAdminUserTraitTest extends TestCase
{
    public function testWithoutMethodsThereIsNoSecondFactor(): void
    {
        $adminUser = new AdminUser();

        self::assertFalse($adminUser->hasTwoFactorAuthentication());
        self::assertFalse($adminUser->isTotpAuthenticationEnabled());
        self::assertNull($adminUser->getTotpAuthenticationConfiguration());
        self::assertNull($adminUser->getPreferredTwoFactorProvider());
    }

    public function testTheAuthenticatorAppUsesSixDigitThirtySecondCodes(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail('admin@example.com');
        $adminUser->setTotpSecret('JBSWY3DPEHPK3PXP');

        $configuration = $adminUser->getTotpAuthenticationConfiguration();

        self::assertTrue($adminUser->hasTwoFactorAuthentication());
        self::assertNotNull($configuration);
        self::assertSame(6, $configuration->getDigits());
        self::assertSame(30, $configuration->getPeriod());
        self::assertSame('admin@example.com', $adminUser->getTotpAuthenticationUsername());
    }

    public function testAPasskeyIsPreferredOverTheApp(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setTotpSecret('JBSWY3DPEHPK3PXP');
        $adminUser->setPasskeyCredentials([['id' => 'a', 'name' => 'Laptop', 'publicKey' => 'pem', 'signCount' => 0, 'createdAt' => '2026-01-01T00:00:00+00:00', 'lastUsedAt' => null]]);

        self::assertSame(PasskeyTwoFactorProvider::ALIAS, $adminUser->getPreferredTwoFactorProvider());
    }

    public function testRemovingTheLastPasskeyLeavesAnEmptyList(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setPasskeyCredentials([['id' => 'a', 'name' => 'Laptop', 'publicKey' => 'pem', 'signCount' => 0, 'createdAt' => '2026-01-01T00:00:00+00:00', 'lastUsedAt' => null]]);
        $adminUser->setPasskeyCredentials([]);

        self::assertSame([], $adminUser->getPasskeyCredentials());
        self::assertFalse($adminUser->hasPasskeys());
    }
}
