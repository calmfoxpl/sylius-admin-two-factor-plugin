<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Unit\Totp;

use Calmfox\SyliusAdminTwoFactorPlugin\Totp\AdminTotpFormRenderer;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Sylius\Component\Core\Model\ShopUser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserInterface;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AdminTotpFormRendererTest extends TestCase
{
    public function testAdministratorsGetTheAdminTemplate(): void
    {
        $inner = $this->createMock(TwoFactorFormRendererInterface::class);
        $inner->expects(self::never())->method('renderForm');

        $response = $this->renderer($inner, new AdminUser())->renderForm(new Request(), ['provider' => 'totp']);

        self::assertSame('admin form: totp', $response->getContent());
    }

    public function testOtherUsersGetTheDecoratedRenderer(): void
    {
        $inner = $this->createMock(TwoFactorFormRendererInterface::class);
        $inner->expects(self::once())->method('renderForm')->willReturn(new Response('shop form'));

        self::assertSame('shop form', $this->renderer($inner, new ShopUser())->renderForm(new Request(), [])->getContent());
    }

    private function renderer(TwoFactorFormRendererInterface $inner, UserInterface $user): AdminTotpFormRenderer
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));

        return new AdminTotpFormRenderer($inner, $tokenStorage, new Environment(new ArrayLoader(['admin.html.twig' => 'admin form: {{ provider }}'])), 'admin.html.twig');
    }
}
