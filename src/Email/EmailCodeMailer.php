<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Email;

use Calmfox\SyliusAdminTwoFactorPlugin\Model\TwoFactorAdminUserInterface;
use Sylius\Component\Mailer\Sender\SenderInterface;

/**
 * Sends the code through Sylius' mailer, so it goes out with the shop's sender address and
 * transport, in the administrator's own panel language.
 */
final readonly class EmailCodeMailer
{
    public const EMAIL_CODE = 'calmfox_admin_two_factor_code';

    public function __construct(private SenderInterface $emailSender)
    {
    }

    public function send(TwoFactorAdminUserInterface $adminUser, string $code, string $purpose, int $validMinutes): void
    {
        $this->emailSender->send(self::EMAIL_CODE, [(string) $adminUser->getEmail()], [
            'adminUser' => $adminUser,
            'code' => $code,
            'purpose' => $purpose,
            'validMinutes' => $validMinutes,
            'localeCode' => $adminUser->getLocaleCode(),
        ]);
    }
}
