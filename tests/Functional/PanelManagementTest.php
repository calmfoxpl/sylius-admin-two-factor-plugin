<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Functional;

use Calmfox\SyliusAdminTwoFactorPlugin\Policy\TwoFactorPolicy;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class PanelManagementTest extends FunctionalTestCase
{
    public function testThePolicyIsSetInConfiguration(): void
    {
        $this->client->loginUser($this->createAdmin(self::TOTP_SECRET), 'admin');

        $crawler = $this->client->request('GET', '/admin/two-factor/settings');
        self::assertResponseIsSuccessful();
        self::assertCount(3, $crawler->filter('input[name="calmfox_admin_two_factor_settings[policy]"]'));

        $form = $crawler->filter('form[name="calmfox_admin_two_factor_settings"]')->form();
        $form['calmfox_admin_two_factor_settings[policy]'] = TwoFactorPolicy::OPTIONAL;
        $this->client->submit($form);

        self::assertResponseRedirects('/admin/two-factor/settings');
        self::assertSame(TwoFactorPolicy::OPTIONAL, $this->service('calmfox_admin_two_factor.policy', TwoFactorPolicy::class)->current());
    }

    public function testResetRemovesTheMethodsAndRequiresANewPairing(): void
    {
        $target = $this->createAdmin(self::TOTP_SECRET);
        $this->client->loginUser($this->createAdmin(self::TOTP_SECRET), 'admin');

        $crawler = $this->client->request('GET', '/admin/users/' . self::id($target) . '/edit');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->filter('form#calmfox-admin-two-factor-reset')->form());

        self::assertResponseRedirects('/admin/users/' . self::id($target) . '/edit');
        $reset = $this->reload($target);
        self::assertFalse($reset->hasTwoFactorAuthentication());
        self::assertTrue($reset->isTwoFactorSetupRequired());

        $this->setPolicy(TwoFactorPolicy::OPTIONAL);
        $this->client->request('GET', '/admin/logout');
        $this->logIn($reset);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/two-factor/setup', null, 'a reset account pairs again even with an optional policy');
    }

    public function testTurningOffRemovesTheMethodsAndLeavesTheRestToThePolicy(): void
    {
        $target = $this->createAdmin(self::TOTP_SECRET);
        $this->client->loginUser($this->createAdmin(self::TOTP_SECRET), 'admin');

        $crawler = $this->client->request('GET', '/admin/users/' . self::id($target) . '/edit');
        $this->client->submit($crawler->filter('form#calmfox-admin-two-factor-disable')->form());

        $disabled = $this->reload($target);
        self::assertFalse($disabled->hasTwoFactorAuthentication());
        self::assertFalse($disabled->isTwoFactorSetupRequired());
    }

    public function testManagingAnotherAdministratorRequiresTheCsrfToken(): void
    {
        $target = $this->createAdmin(self::TOTP_SECRET);
        $this->client->loginUser($this->createAdmin(self::TOTP_SECRET), 'admin');

        $this->client->request('POST', '/admin/users/' . self::id($target) . '/two-factor/reset', ['_csrf_token' => 'forged']);

        self::assertResponseStatusCodeSame(403);
        self::assertTrue($this->reload($target)->hasTwoFactorAuthentication());
    }

    public function testTheGridShowsWhoHasASecondFactor(): void
    {
        $this->client->loginUser($this->createAdmin(self::TOTP_SECRET), 'admin');

        $crawler = $this->client->request('GET', '/admin/users/');

        self::assertResponseIsSuccessful();
        self::assertGreaterThan(0, $crawler->filter('th.sylius-table-column-twoFactor')->count());
    }

    public function testTheConsoleCommandResetsAndDisables(): void
    {
        $target = $this->createAdmin(self::TOTP_SECRET);
        self::assertNotNull(self::$kernel);
        $tester = new CommandTester((new Application(self::$kernel))->find('calmfox:admin:2fa:reset'));

        $tester->execute(['email' => $target->getEmail(), '--disable' => true]);
        self::assertFalse($this->reload($target)->isTwoFactorSetupRequired());
        self::assertFalse($this->reload($target)->hasTwoFactorAuthentication());

        $tester->execute(['email' => $target->getEmail()]);
        self::assertTrue($this->reload($target)->isTwoFactorSetupRequired());
    }

    public function testAnAcceptedInvitationLeadsToTwoFactorSetup(): void
    {
        /** @var \Sylius\Resource\Factory\FactoryInterface<\Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser> $factory */
        $factory = $this->service('sylius.factory.admin_user', \Sylius\Resource\Factory\FactoryInterface::class);
        $invitee = $factory->createNew();
        $invitee->setEmail(sprintf('invitee.%s@example.com', bin2hex(random_bytes(4))));
        $invitee->setLocaleCode('en_US');
        \Calmfox\SyliusAdminInvitationPlugin\Invitation\PendingInvitation::prepare($invitee);
        $this->entityManager()->persist($invitee);
        $this->entityManager()->flush();
        $this->service('calmfox_admin_invitation.sender', \Calmfox\SyliusAdminInvitationPlugin\Invitation\InvitationSenderInterface::class)->send($invitee);
        $token = $this->reload($invitee)->getPasswordResetToken();

        $this->client->request('GET', '/admin/invitation/' . $token);
        $this->client->submitForm('Save and log in', [
            'calmfox_admin_invitation_accept[firstName]' => 'Ada',
            'calmfox_admin_invitation_accept[lastName]' => 'Lovelace',
            'calmfox_admin_invitation_accept[password][first]' => self::PASSWORD,
            'calmfox_admin_invitation_accept[password][second]' => self::PASSWORD,
        ]);

        self::assertResponseRedirects('/admin/two-factor/setup');
    }
}
