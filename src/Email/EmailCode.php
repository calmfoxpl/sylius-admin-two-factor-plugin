<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * One-time codes sent by e-mail, for logging in and for turning the method on.
 *
 * The code lives in the session, not on the account: it only ever makes sense in the browser
 * that asked for it, so there is nothing to migrate and nothing left behind in the database
 * when a login is abandoned. Only its hash is kept, it is bound to the administrator and to its
 * purpose (a setup code does not log anyone in), it expires, and a few wrong guesses burn it,
 * so the six digits cannot be walked through. A new one can be asked for once per interval,
 * which keeps the button from flooding a mailbox.
 */
final class EmailCode
{
    public const LOGIN = 'login';

    public const SETUP = 'setup';

    public const SENT = 'sent';

    public const THROTTLED = 'throttled';

    public const FAILED = 'failed';

    public const MAX_ATTEMPTS = 5;

    private const SESSION_KEY = 'calmfox_admin_two_factor.email_code';

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly EmailCodeMailer $mailer,
        private readonly int $lifetime,
        private readonly int $resendInterval,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Sends a fresh code, which replaces any earlier one.
     *
     * @return self::SENT|self::THROTTLED|self::FAILED
     */
    public function send(TwoFactorAdminUserInterface $adminUser, string $purpose): string
    {
        if ($this->secondsUntilResend() > 0) {
            return self::THROTTLED;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', \STR_PAD_LEFT);

        try {
            $this->mailer->send($adminUser, $code, $purpose, $this->lifetimeInMinutes());
        } catch (\Throwable $exception) {
            // a broken mail server must not turn the login page into an error page
            $this->logger->error('The two-factor e-mail code could not be sent: {message}', [
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            return self::FAILED;
        }

        $now = time();
        $this->write([
            'user' => self::owner($adminUser),
            'purpose' => $purpose,
            'hash' => self::hash($code),
            'expiresAt' => $now + $this->lifetime,
            'attempts' => 0,
            'sentAt' => $now,
        ]);

        return self::SENT;
    }

    /** Checks a typed code. A correct code works once; a wrong one counts towards burning it. */
    public function verify(TwoFactorAdminUserInterface $adminUser, string $purpose, string $code): bool
    {
        $record = $this->read();
        if (null === $record || !$this->belongsTo($record, $adminUser, $purpose) || $this->isExpired($record) || $record['attempts'] >= self::MAX_ATTEMPTS) {
            return false;
        }

        if (hash_equals($record['hash'], self::hash(preg_replace('/\s+/', '', $code) ?? ''))) {
            $this->session()?->remove(self::SESSION_KEY);

            return true;
        }

        ++$record['attempts'];
        $this->write($record);

        return false;
    }

    /** A code is waiting in the mailbox and can still be typed. */
    public function isPending(TwoFactorAdminUserInterface $adminUser, string $purpose): bool
    {
        $record = $this->read();

        return null !== $record &&
            $this->belongsTo($record, $adminUser, $purpose) &&
            !$this->isExpired($record) &&
            $record['attempts'] < self::MAX_ATTEMPTS;
    }

    /** Too many wrong guesses: this code no longer works and a new one has to be asked for. */
    public function isBurnt(TwoFactorAdminUserInterface $adminUser, string $purpose): bool
    {
        $record = $this->read();

        return null !== $record &&
            $this->belongsTo($record, $adminUser, $purpose) &&
            !$this->isExpired($record) &&
            $record['attempts'] >= self::MAX_ATTEMPTS;
    }

    public function secondsUntilResend(): int
    {
        $record = $this->read();

        return null === $record ? 0 : max(0, $record['sentAt'] + $this->resendInterval - time());
    }

    public function lifetimeInMinutes(): int
    {
        return max(1, (int) ceil($this->lifetime / 60));
    }

    /** j***@example.com: enough to recognise the mailbox, not enough to read it off a screen. */
    public static function maskedRecipient(TwoFactorAdminUserInterface $adminUser): string
    {
        [$local, $domain] = array_pad(explode('@', (string) $adminUser->getEmail(), 2), 2, '');

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }

    /** @param array{user: string, purpose: string, hash: string, expiresAt: int, attempts: int, sentAt: int} $record */
    private function belongsTo(array $record, TwoFactorAdminUserInterface $adminUser, string $purpose): bool
    {
        return $record['user'] === self::owner($adminUser) && $record['purpose'] === $purpose;
    }

    /** The account and the address the code went to: after an address change an older code no longer counts. */
    private static function owner(TwoFactorAdminUserInterface $adminUser): string
    {
        $id = $adminUser->getId();

        return sprintf('%s|%s', is_int($id) || is_string($id) ? $id : '', (string) $adminUser->getEmail());
    }

    /** @param array{user: string, purpose: string, hash: string, expiresAt: int, attempts: int, sentAt: int} $record */
    private function isExpired(array $record): bool
    {
        return time() >= $record['expiresAt'];
    }

    /** @return array{user: string, purpose: string, hash: string, expiresAt: int, attempts: int, sentAt: int}|null */
    private function read(): ?array
    {
        $record = $this->session()?->get(self::SESSION_KEY);
        if (!is_array($record) ||
            !is_string($record['user'] ?? null) ||
            !is_string($record['purpose'] ?? null) ||
            !is_string($record['hash'] ?? null) ||
            !is_int($record['expiresAt'] ?? null) ||
            !is_int($record['attempts'] ?? null) ||
            !is_int($record['sentAt'] ?? null)
        ) {
            return null;
        }

        return [
            'user' => $record['user'],
            'purpose' => $record['purpose'],
            'hash' => $record['hash'],
            'expiresAt' => $record['expiresAt'],
            'attempts' => $record['attempts'],
            'sentAt' => $record['sentAt'],
        ];
    }

    /** @param array{user: string, purpose: string, hash: string, expiresAt: int, attempts: int, sentAt: int} $record */
    private function write(array $record): void
    {
        $this->session()?->set(self::SESSION_KEY, $record);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requestStack->getMainRequest();

        return null !== $request && $request->hasSession() ? $request->getSession() : null;
    }

    private static function hash(string $code): string
    {
        return hash('sha256', $code);
    }
}
