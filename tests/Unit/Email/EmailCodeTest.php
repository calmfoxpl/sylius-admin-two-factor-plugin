<?php

declare(strict_types=1);

namespace Tests\Calmfox\SyliusAdminTwoFactorPlugin\Unit\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCode;
use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCodeMailer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Sylius\Component\Mailer\Sender\SenderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Tests\Calmfox\SyliusAdminTwoFactorPlugin\TestApplication\Entity\AdminUser;

final class EmailCodeTest extends TestCase
{
    /** @var list<array{recipients: array<mixed>, data: array<mixed>}> */
    private array $sent = [];

    private bool $mailServerDown = false;

    /** @var \ArrayObject<int, string> */
    private \ArrayObject $logged;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $this->logged = new \ArrayObject();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack();
        $this->requestStack->push($request);
    }

    public function testTheCodeGoesToTheAccountAddressAndWorksOnce(): void
    {
        $emailCode = $this->emailCode();
        $adminUser = self::admin('anna@example.com');

        self::assertSame(EmailCode::SENT, $emailCode->send($adminUser, EmailCode::LOGIN));
        self::assertSame(['anna@example.com'], $this->sent[0]['recipients']);
        self::assertTrue($emailCode->isPending($adminUser, EmailCode::LOGIN));

        $code = $this->lastCode();
        self::assertMatchesRegularExpression('/^\d{6}$/', $code);
        self::assertTrue($emailCode->verify($adminUser, EmailCode::LOGIN, $code));
        self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $code), 'a used code does not work again');
        self::assertFalse($emailCode->isPending($adminUser, EmailCode::LOGIN));
    }

    public function testSpacesTypedInTheCodeAreIgnored(): void
    {
        $emailCode = $this->emailCode();
        $adminUser = self::admin('anna@example.com');
        $emailCode->send($adminUser, EmailCode::LOGIN);
        $code = $this->lastCode();

        self::assertTrue($emailCode->verify($adminUser, EmailCode::LOGIN, substr($code, 0, 3) . ' ' . substr($code, 3)));
    }

    public function testASetupCodeDoesNotLogIn(): void
    {
        $emailCode = $this->emailCode();
        $adminUser = self::admin('anna@example.com');
        $emailCode->send($adminUser, EmailCode::SETUP);

        self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $this->lastCode()));
        self::assertTrue($emailCode->verify($adminUser, EmailCode::SETUP, $this->lastCode()));
    }

    public function testACodeIsBoundToTheAdministratorItWasSentTo(): void
    {
        $emailCode = $this->emailCode();
        $emailCode->send(self::admin('anna@example.com'), EmailCode::LOGIN);

        self::assertFalse($emailCode->verify(self::admin('bob@example.com'), EmailCode::LOGIN, $this->lastCode()));
    }

    public function testWrongGuessesBurnTheCode(): void
    {
        $emailCode = $this->emailCode();
        $adminUser = self::admin('anna@example.com');
        $emailCode->send($adminUser, EmailCode::LOGIN);
        $code = $this->lastCode();
        $wrong = '000000' === $code ? '111111' : '000000';

        for ($i = 0; $i < EmailCode::MAX_ATTEMPTS; ++$i) {
            self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $wrong));
        }

        self::assertTrue($emailCode->isBurnt($adminUser, EmailCode::LOGIN));
        self::assertFalse($emailCode->isPending($adminUser, EmailCode::LOGIN));
        self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $code), 'even the right code no longer works');
    }

    public function testAnExpiredCodeIsRejected(): void
    {
        $emailCode = $this->emailCode(lifetime: 0);
        $adminUser = self::admin('anna@example.com');
        $emailCode->send($adminUser, EmailCode::LOGIN);

        self::assertFalse($emailCode->isPending($adminUser, EmailCode::LOGIN));
        self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $this->lastCode()));
    }

    public function testANewCodeWaitsForTheResendInterval(): void
    {
        $emailCode = $this->emailCode(resendInterval: 60);
        $adminUser = self::admin('anna@example.com');

        self::assertSame(EmailCode::SENT, $emailCode->send($adminUser, EmailCode::LOGIN));
        self::assertSame(EmailCode::THROTTLED, $emailCode->send($adminUser, EmailCode::LOGIN));
        self::assertCount(1, $this->sent, 'the second request sends nothing');
        self::assertGreaterThan(0, $emailCode->secondsUntilResend());
    }

    public function testANewCodeReplacesTheOldOne(): void
    {
        $emailCode = $this->emailCode(resendInterval: 0);
        $adminUser = self::admin('anna@example.com');
        $emailCode->send($adminUser, EmailCode::LOGIN);
        $first = $this->lastCode();
        do {
            $this->sent = [];
            $emailCode->send($adminUser, EmailCode::LOGIN);
        } while ($this->lastCode() === $first);

        self::assertFalse($emailCode->verify($adminUser, EmailCode::LOGIN, $first));
        self::assertTrue($emailCode->verify($adminUser, EmailCode::LOGIN, $this->lastCode()));
    }

    public function testABrokenMailServerIsReportedAndLeavesNoCodeBehind(): void
    {
        $this->mailServerDown = true;
        $emailCode = $this->emailCode();
        $adminUser = self::admin('anna@example.com');

        self::assertSame(EmailCode::FAILED, $emailCode->send($adminUser, EmailCode::LOGIN));
        self::assertFalse($emailCode->isPending($adminUser, EmailCode::LOGIN));
        self::assertSame(0, $emailCode->secondsUntilResend(), 'trying again is not throttled');
        self::assertCount(1, $this->logged);
    }

    public function testTheRecipientIsMaskedOnScreen(): void
    {
        self::assertSame('a***@example.com', EmailCode::maskedRecipient(self::admin('anna@example.com')));
    }

    public function testTheLifetimeIsShownInWholeMinutes(): void
    {
        self::assertSame(10, $this->emailCode(lifetime: 600)->lifetimeInMinutes());
        self::assertSame(2, $this->emailCode(lifetime: 90)->lifetimeInMinutes());
    }

    private function emailCode(int $lifetime = 600, int $resendInterval = 60): EmailCode
    {
        $sender = $this->createMock(SenderInterface::class);
        $sender->method('send')->willReturnCallback(function (string $code, array $recipients, array $data): void {
            if ($this->mailServerDown) {
                throw new \RuntimeException('Connection refused');
            }
            $this->sent[] = ['recipients' => $recipients, 'data' => $data];
        });

        $logger = new class($this->logged) extends AbstractLogger {
            /** @param \ArrayObject<int, string> $logged */
            public function __construct(private readonly \ArrayObject $logged)
            {
            }

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->logged[] = (string) $message;
            }
        };

        return new EmailCode($this->requestStack, new EmailCodeMailer($sender), $lifetime, $resendInterval, $logger);
    }

    private function lastCode(): string
    {
        $last = end($this->sent);
        self::assertIsArray($last);
        $code = $last['data']['code'] ?? null;
        self::assertIsString($code);

        return $code;
    }

    private static function admin(string $email): AdminUser
    {
        $adminUser = new AdminUser();
        $adminUser->setEmail($email);
        $adminUser->setUsername($email);

        return $adminUser;
    }
}
