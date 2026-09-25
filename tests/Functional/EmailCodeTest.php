<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Functional;

use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCode;
use Symfony\Component\Mime\Email;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

final class EmailCodeTest extends FunctionalTestCase
{
    public function testAnAdministratorTurnsOnEmailCodesByTypingBackTheFirstOne(): void
    {
        $adminUser = $this->createAdmin();
        $this->logIn($adminUser);

        $crawler = $this->client->request('GET', '/admin/two-factor/setup');
        self::assertCount(1, $crawler->filter('#calmfox-two-factor-email'), 'e-mail codes are offered');
        self::assertCount(0, $crawler->filter('#calmfox-two-factor-email-code'), 'no code field before a code is sent');

        $this->client->submitForm('Send me a code');
        self::assertResponseRedirects('/admin/two-factor/setup#calmfox-two-factor-email');
        self::assertSame([(string) $adminUser->getEmail()], $this->recipientsOfLastEmail());
        $code = $this->codeFromLastEmail();

        $this->client->followRedirect();
        self::assertSelectorExists('#calmfox-two-factor-email-code');
        $this->client->submitForm('Confirm and turn on', ['code' => self::otherThan($code)]);
        self::assertResponseRedirects('/admin/two-factor/setup#calmfox-two-factor-email');
        self::assertFalse($this->reload($adminUser)->isEmailCodeEnabled(), 'nothing is turned on before a correct code');

        $this->client->followRedirect();
        $this->client->submitForm('Confirm and turn on', ['code' => $code]);
        self::assertResponseRedirects('/admin/');
        self::assertTrue($this->reload($adminUser)->isEmailCodeEnabled());

        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
    }

    public function testLoginSendsACodeAndAcceptsIt(): void
    {
        $adminUser = $this->createEmailAdmin();
        $this->logIn($adminUser);

        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/2fa');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(1, self::getMailerMessages());
        self::assertSelectorTextContains('.form-hint', 'a***@');
        $code = $this->codeFromLastEmail();

        // the mailer log only holds the last request's messages
        $this->client->request('GET', '/admin/2fa');
        self::assertCount(0, self::getMailerMessages(), 'reloading the page does not send another code');

        $this->client->submitForm('Verify', ['_auth_code' => self::otherThan($code)]);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/2fa', null, 'a wrong code does not open the panel');

        $this->client->request('GET', '/admin/2fa');
        $this->client->submitForm('Verify', ['_auth_code' => $code]);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
    }

    public function testANewCodeHasToWaitForTheResendInterval(): void
    {
        $this->logIn($this->createEmailAdmin());
        $this->client->request('GET', '/admin/2fa');
        self::assertCount(1, self::getMailerMessages());

        $this->client->submitForm('Send a new code');
        self::assertResponseRedirects('/admin/2fa?preferProvider=admin_email');
        self::assertCount(0, self::getMailerMessages(), 'nothing is sent within the interval');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'You can ask for a new code in');
    }

    public function testTooManyWrongCodesBurnTheCode(): void
    {
        $this->logIn($this->createEmailAdmin());
        $this->client->request('GET', '/admin/2fa');
        $code = $this->codeFromLastEmail();

        for ($i = 0; $i < EmailCode::MAX_ATTEMPTS; ++$i) {
            $this->client->request('GET', '/admin/2fa');
            $this->client->submitForm('Verify', ['_auth_code' => self::otherThan($code)]);
        }

        $this->client->request('GET', '/admin/2fa');
        self::assertSelectorTextContains('.alert-warning', 'Too many wrong codes');
        self::assertCount(0, self::getMailerMessages(), 'a burnt code is not replaced without asking');

        $this->client->submitForm('Verify', ['_auth_code' => $code]);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseRedirects('/admin/2fa', null, 'the burnt code no longer works');
    }

    public function testAnAdministratorWithTheAppCanFallBackToAnEmail(): void
    {
        $adminUser = $this->createEmailAdmin(self::TOTP_SECRET);
        $this->logIn($adminUser);

        $crawler = $this->client->request('GET', '/admin/2fa');
        self::assertCount(1, $crawler->filter('input[name="_auth_code"][maxlength="6"]'), 'the app is offered first');
        self::assertCount(1, $crawler->filter('a[href*="preferProvider=admin_email"]'));
        self::assertCount(0, self::getMailerMessages(), 'no e-mail until asked for');

        $this->client->request('GET', '/admin/2fa?preferProvider=admin_email');
        self::assertCount(1, self::getMailerMessages());
        self::assertSelectorExists('a[href*="preferProvider=totp"]');

        $this->client->submitForm('Verify', ['_auth_code' => $this->codeFromLastEmail()]);
        $this->client->request('GET', '/admin/users/');
        self::assertResponseIsSuccessful();
    }

    private function createEmailAdmin(?string $totpSecret = null): AdminUser
    {
        $adminUser = $this->createAdmin($totpSecret);
        $adminUser->setEmailCodeEnabled(true);
        $this->entityManager()->flush();

        return $adminUser;
    }

    private function lastEmail(): Email
    {
        $messages = self::getMailerMessages();
        $last = end($messages);
        self::assertInstanceOf(Email::class, $last);

        return $last;
    }

    /** @return list<string> */
    private function recipientsOfLastEmail(): array
    {
        return array_map(static fn ($address): string => $address->getAddress(), array_values($this->lastEmail()->getTo()));
    }

    private function codeFromLastEmail(): string
    {
        self::assertSame(1, preg_match('/>\s*(\d{6})\s*</', (string) $this->lastEmail()->getHtmlBody(), $matches));

        return $matches[1];
    }

    private static function otherThan(string $code): string
    {
        return '000000' === $code ? '111111' : '000000';
    }
}
