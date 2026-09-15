<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Functional;

use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Sylius\Component\User\Repository\UserRepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

abstract class FunctionalTestCase extends WebTestCase
{
    protected const PASSWORD = 'kmv53p!L8za7KgjZh_AF';

    protected const TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->setPolicy(TwoFactorPolicy::REQUIRED);
    }

    protected function setPolicy(string $policy): void
    {
        $service = self::getContainer()->get('calmfox_admin_two_factor.policy');
        self::assertInstanceOf(TwoFactorPolicy::class, $service);
        $service->change($policy);
    }

    protected function createAdmin(?string $totpSecret = null): AdminUser
    {
        /** @var FactoryInterface<AdminUser> $factory */
        $factory = $this->service('sylius.factory.admin_user', FactoryInterface::class);
        $adminUser = $factory->createNew();
        $email = sprintf('admin.%s@example.com', bin2hex(random_bytes(4)));
        $adminUser->setEmail($email);
        $adminUser->setUsername($email);
        $adminUser->setLocaleCode('en_US');
        $adminUser->setEnabled(true);
        $adminUser->setPlainPassword(self::PASSWORD);
        $adminUser->setTotpSecret($totpSecret);

        $this->entityManager()->persist($adminUser);
        $this->entityManager()->flush();

        return $adminUser;
    }

    /** Logs in through the real login form, so two-factor authentication kicks in. */
    protected function logIn(AdminUser $adminUser): void
    {
        $this->client->request('GET', '/admin/login');
        $this->client->submitForm('Login', ['_username' => (string) $adminUser->getEmail(), '_password' => self::PASSWORD]);
    }

    protected function reload(AdminUser $adminUser): AdminUser
    {
        $this->entityManager()->clear();
        /** @var UserRepositoryInterface<AdminUser> $repository */
        $repository = $this->service('sylius.repository.admin_user', UserRepositoryInterface::class);
        $fresh = $repository->find($adminUser->getId());
        self::assertInstanceOf(AdminUser::class, $fresh);

        return $fresh;
    }

    /** @param non-empty-string $secret */
    protected static function totp(string $secret): string
    {
        // a code from the next half-minute would be rejected, so do not start at its very end
        if (time() % 30 > 27) {
            sleep(3);
        }

        return TOTP::createFromSecret($secret)->now();
    }

    protected static function id(AdminUser $adminUser): string
    {
        $id = $adminUser->getId();
        self::assertIsInt($id);

        return (string) $id;
    }

    protected function entityManager(): EntityManagerInterface
    {
        return $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    protected function service(string $id, string $type): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($type, $service);

        return $service;
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<string, mixed>
     */
    protected function postJson(string $path, string $csrf, array $body = []): array
    {
        $this->client->request('POST', $path, server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => $csrf], content: json_encode($body, \JSON_THROW_ON_ERROR));
        /** @var array<string, mixed> $data */
        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        return $data;
    }
}
