<?php

declare(strict_types=1);

namespace Calmfox\SyliusAdminTwoFactorPlugin\Model;

use Calmfox\SyliusAdminTwoFactorPlugin\Email\EmailCodeTwoFactorProvider;
use Calmfox\SyliusAdminTwoFactorPlugin\Passkey\PasskeyTwoFactorProvider;
use Doctrine\ORM\Mapping as ORM;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;

/**
 * Use in your AdminUser entity together with TwoFactorAdminUserInterface. Adds four columns:
 * the TOTP secret, the list of passkeys (public keys only — the private key never
 * leaves the administrator's device), the e-mail code switch and the
 * "pair again at next login" flag.
 */
trait TwoFactorAdminUserTrait
{
    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    protected ?string $totpSecret = null;

    /** @var list<array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}>|null */
    #[ORM\Column(name: 'passkey_credentials', type: 'json', nullable: true)]
    protected ?array $passkeyCredentials = null;

    #[ORM\Column(name: 'email_code_enabled', type: 'boolean', options: ['default' => false])]
    protected bool $emailCodeEnabled = false;

    #[ORM\Column(name: 'two_factor_setup_required', type: 'boolean', options: ['default' => false])]
    protected bool $twoFactorSetupRequired = false;

    public function isTwoFactorSetupRequired(): bool
    {
        return $this->twoFactorSetupRequired;
    }

    public function setTwoFactorSetupRequired(bool $twoFactorSetupRequired): void
    {
        $this->twoFactorSetupRequired = $twoFactorSetupRequired;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): void
    {
        $this->totpSecret = $totpSecret;
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return (string) ($this->getEmail() ?? $this->getUsername());
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        return null === $this->totpSecret
            ? null
            : new TotpConfiguration($this->totpSecret, TotpConfiguration::ALGORITHM_SHA1, 30, 6);
    }

    public function getPasskeyCredentials(): array
    {
        return $this->passkeyCredentials ?? [];
    }

    /** @param array<int, array{id: string, name: string, publicKey: string, signCount: int, createdAt: string, lastUsedAt: string|null}> $passkeyCredentials */
    public function setPasskeyCredentials(array $passkeyCredentials): void
    {
        $this->passkeyCredentials = [] === $passkeyCredentials ? null : array_values($passkeyCredentials);
    }

    public function hasPasskeys(): bool
    {
        return [] !== $this->getPasskeyCredentials();
    }

    public function isEmailCodeEnabled(): bool
    {
        return $this->emailCodeEnabled;
    }

    public function setEmailCodeEnabled(bool $emailCodeEnabled): void
    {
        $this->emailCodeEnabled = $emailCodeEnabled;
    }

    public function hasTwoFactorAuthentication(): bool
    {
        return $this->isTotpAuthenticationEnabled() || $this->hasPasskeys() || $this->emailCodeEnabled;
    }

    public function clearTwoFactorAuthentication(): void
    {
        $this->totpSecret = null;
        $this->passkeyCredentials = null;
        $this->emailCodeEnabled = false;
    }

    /**
     * A passkey is one gesture, an app code is at hand at once, and an e-mail means waiting for
     * the message, so that is the order offered at login. The others stay one click away.
     */
    public function getPreferredTwoFactorProvider(): ?string
    {
        return match (true) {
            $this->hasPasskeys() => PasskeyTwoFactorProvider::ALIAS,
            $this->isTotpAuthenticationEnabled() => 'totp',
            $this->emailCodeEnabled => EmailCodeTwoFactorProvider::ALIAS,
            default => null,
        };
    }
}
