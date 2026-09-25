<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Model;

use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Sylius\Component\Core\Model\AdminUserInterface;

/**
 * An administrator's second factor: passkeys, an authenticator app (TOTP), a code sent by
 * e-mail, or any mix of them. Which one to set up, and which one to use at login, is the
 * administrator's choice.
 */
interface TwoFactorAdminUserInterface extends AdminUserInterface, TwoFactorInterface, PreferredProviderInterface
{
    public function getTotpSecret(): ?string;

    public function setTotpSecret(?string $totpSecret): void;

    /**
     * @return list<array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}>
     */
    public function getPasskeyCredentials(): array;

    /**
     * @param array<int, array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}> $passkeyCredentials
     */
    public function setPasskeyCredentials(array $passkeyCredentials): void;

    public function hasPasskeys(): bool;

    /** Codes sent to the account's e-mail address. The code itself is kept in the session, never on the account. */
    public function isEmailCodeEnabled(): bool;

    public function setEmailCodeEnabled(bool $emailCodeEnabled): void;

    /** Set when another administrator resets this one's 2FA: pair again at next login, whatever the policy. */
    public function isTwoFactorSetupRequired(): bool;

    public function setTwoFactorSetupRequired(bool $twoFactorSetupRequired): void;

    /** True once the administrator has any second factor: a passkey, an authenticator app or e-mail codes. */
    public function hasTwoFactorAuthentication(): bool;

    /** Removes every method: passkeys, the authenticator app and e-mail codes. */
    public function clearTwoFactorAuthentication(): void;
}
