<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Functional;

use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;

final class AuthenticatorAppTest extends FunctionalTestCase
{
    public function testWithARequiredPolicyAnAdministratorPairsAnAppBeforeUsingThePanel(): void
    {
        $adminUser = $this->createAdmin();
        $this->logIn($adminUser);

        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/two-factor/setup');

        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-calmfox-passkey-setup]'), 'a passkey is offered');
        self::assertCount(1, $crawler->filter('img[src^="data:image/svg+xml;base64,"]'), 'a QR code is shown');
        $secret = str_replace(' ', '', $crawler->filter('code')->text());
        self::assertNotSame('', $secret);

        $this->client->submitForm('Turn on and continue', ['calmfox_admin_two_factor_setup[code]' => '000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->reload($adminUser)->getTotpSecret(), 'nothing is stored before a correct code');

        $this->client->submitForm('Turn on and continue', ['calmfox_admin_two_factor_setup[code]' => self::totp($secret)]);
        self::assertResponseRedirects('/admin/');

        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
        self::assertSame($secret, $this->reload($adminUser)->getTotpSecret());
    }

    public function testLoginAsksForTheCodeAfterThePassword(): void
    {
        $adminUser = $this->createAdmin(self::TOTP_SECRET);
        $this->logIn($adminUser);

        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/2fa');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_auth_code"][autocomplete="one-time-code"]');

        $this->client->submitForm('Verify', ['_auth_code' => '000000']);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/2fa', null, 'a wrong code does not open the panel');

        $this->client->request('GET', '/admin/2fa');
        $this->client->submitForm('Verify', ['_auth_code' => self::totp(self::TOTP_SECRET)]);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
    }

    public function testTurnedOffPolicyDoesNotAskForTheCode(): void
    {
        $this->setPolicy(TwoFactorPolicy::DISABLED);
        $this->logIn($this->createAdmin(self::TOTP_SECRET));

        $this->client->request('GET', '/admin/users/');

        self::assertResponseIsSuccessful();
    }

    public function testOptionalPolicyLetsAnAdministratorWithoutASecondFactorIn(): void
    {
        $this->setPolicy(TwoFactorPolicy::OPTIONAL);
        $this->logIn($this->createAdmin());

        $this->client->request('GET', '/admin/users/');

        self::assertResponseIsSuccessful();
    }
}
