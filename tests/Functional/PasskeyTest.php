<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Functional;

use Tests\Calmfox\SyliusAdminTwoFactorPlugin\Support\SoftwareAuthenticator;

final class PasskeyTest extends FunctionalTestCase
{
    private const ORIGIN = 'http://localhost';

    public function testAnAdministratorPairsAPasskeyAndLogsInWithIt(): void
    {
        $adminUser = $this->createAdmin();
        $authenticator = new SoftwareAuthenticator('localhost');
        $this->logIn($adminUser);

        $crawler = $this->client->request('GET', '/admin/two-factor/setup');
        $csrf = (string) $crawler->filter('[data-calmfox-passkey-setup]')->attr('data-csrf');

        $options = $this->options($this->postJson('/admin/two-factor/passkey/options', $csrf));
        self::assertResponseIsSuccessful();

        $result = $this->postJson('/admin/two-factor/passkey', $csrf, ['credential' => $authenticator->register($options, self::ORIGIN), 'name' => 'Laptop']);
        self::assertResponseIsSuccessful();
        self::assertSame('/admin/', $result['redirect'] ?? null);
        self::assertTrue($this->reload($adminUser)->hasPasskeys());

        $this->client->request('GET', '/admin/logout');
        $this->logIn($adminUser);
        $crawler = $this->client->request('GET', '/admin/2fa');
        self::assertCount(1, $crawler->filter('[data-calmfox-passkey-login]'), 'the passkey is offered first');

        $loginCsrf = (string) $crawler->filter('[data-calmfox-passkey-login]')->attr('data-csrf');
        $loginOptions = $this->options($this->postJson('/admin/2fa/passkey/options', $loginCsrf));

        $form = $crawler->filter('[data-calmfox-passkey-login] form')->form();
        $form['_auth_code'] = json_encode($authenticator->login($loginOptions, self::ORIGIN), \JSON_THROW_ON_ERROR);
        $this->client->submit($form);

        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
    }

    public function testThePasskeyEndpointsRequireTheCsrfToken(): void
    {
        $this->logIn($this->createAdmin());

        $this->client->request('POST', '/admin/two-factor/passkey/options', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'forged'], content: '{}');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnAdministratorWithBothMethodsCanSwitchToTheCode(): void
    {
        $adminUser = $this->createAdmin(self::TOTP_SECRET);
        $adminUser->setPasskeyCredentials([['id' => 'AAAA', 'name' => 'Laptop', 'publicKey' => 'unused', 'signCount' => 0, 'createdAt' => '2026-01-01T00:00:00+00:00', 'lastUsedAt' => null]]);
        $this->entityManager()->flush();
        $this->logIn($adminUser);

        $crawler = $this->client->request('GET', '/admin/2fa');
        self::assertCount(1, $crawler->filter('a[href*="preferProvider=totp"]'));

        $crawler = $this->client->request('GET', '/admin/2fa?preferProvider=totp');
        self::assertCount(1, $crawler->filter('input[name="_auth_code"][inputmode="numeric"]'));
        self::assertCount(1, $crawler->filter('a[href*="preferProvider=passkey"]'));
    }

    /**
     * @param array<string, mixed> $response
     *
     * @return array<string, mixed>
     */
    private function options(array $response): array
    {
        self::assertIsArray($response['options'] ?? null);
        /** @var array<string, mixed> $options */
        $options = $response['options'];

        return $options;
    }
}
