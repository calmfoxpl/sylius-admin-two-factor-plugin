<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Model;

use Scheb\TwoFactorBundle\Model\PreferredProviderInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Sylius\Component\Core\Model\AdminUserInterface;

/**
 * An administrator's second factor: an authenticator app (TOTP), passkeys, or both.
 * Which one to set up — and which one to use at login — is the administrator's choice.
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

    /** Set when another administrator resets this one's 2FA: pair again at next login, whatever the policy. */
    public function isTwoFactorSetupRequired(): bool;

    public function setTwoFactorSetupRequired(bool $twoFactorSetupRequired): void;

    /** True once the administrator has any second factor: an authenticator app or a passkey. */
    public function hasTwoFactorAuthentication(): bool;
}
