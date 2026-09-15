<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Unit\EventListener;

use Calmfox\SyliusAdminTwoFactorPlugin\Entity\TwoFactorSettings;
use Calmfox\SyliusAdminTwoFactorPlugin\EventListener\EnforceTwoFactorSetupListener;
use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

final class EnforceTwoFactorSetupListenerTest extends TestCase
{
    public function testAnAdministratorWithoutASecondFactorIsSentToSetup(): void
    {
        $event = $this->event('sylius_admin_dashboard');

        $this->listener(new AdminUser())($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/two-factor/setup', $response->getTargetUrl());
    }

    /** @return iterable<string, array{string}> */
    public static function allowedRoutes(): iterable
    {
        yield 'setup page' => ['calmfox_admin_two_factor_setup'];
        yield 'passkey pairing' => ['calmfox_admin_two_factor_passkey_register'];
        yield 'log out' => ['sylius_admin_logout'];
        yield 'profiler' => ['_wdt'];
    }

    #[DataProvider('allowedRoutes')]
    public function testTheWayOutIsNeverBlocked(string $route): void
    {
        $event = $this->event($route);

        $this->listener(new AdminUser())($event);

        self::assertNull($event->getResponse());
    }

    public function testAnAdministratorWithASecondFactorIsLeftAlone(): void
    {
        $adminUser = new AdminUser();
        $adminUser->setTotpSecret('JBSWY3DPEHPK3PXP');
        $event = $this->event('sylius_admin_dashboard');

        $this->listener($adminUser)($event);

        self::assertNull($event->getResponse());
    }

    public function testBackgroundRequestsAreNotRedirected(): void
    {
        $event = $this->event('sylius_admin_dashboard');
        $event->getRequest()->headers->set('X-Requested-With', 'XMLHttpRequest');

        $this->listener(new AdminUser())($event);

        self::assertNull($event->getResponse());
    }

    public function testOtherFirewallsAreNotItsBusiness(): void
    {
        $event = $this->event('sylius_shop_homepage');

        $this->listener(new AdminUser(), 'shop')($event);

        self::assertNull($event->getResponse());
    }

    private function listener(AdminUser $adminUser, string $firewall = 'admin'): EnforceTwoFactorSetupListener
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($adminUser, 'admin'));

        $firewallMap = $this->createMock(FirewallMap::class);
        $firewallMap->method('getFirewallConfig')->willReturn(new FirewallConfig($firewall, 'security.user_checker'));

        $router = $this->createMock(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/two-factor/setup');

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(new TwoFactorSettings(TwoFactorPolicy::REQUIRED));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willReturn($repository);

        return new EnforceTwoFactorSetupListener($tokenStorage, $firewallMap, $router, new TwoFactorPolicy($entityManager, TwoFactorPolicy::REQUIRED), 'admin');
    }

    private function event(string $route): RequestEvent
    {
        $request = new Request();
        $request->attributes->set('_route', $route);

        return new RequestEvent($this->createMock(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
