<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\AdminRepository;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(
    name: 'symfonicat_admin',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_symfonicat_admin_email', columns: ['email']),
    ],
)]
class Admin implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    private const TOTP_ALGORITHM = TotpConfiguration::ALGORITHM_SHA1;
    private const TOTP_PERIOD = 30;
    private const TOTP_DIGITS = 6;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email = '';

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private string $password = '';

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $mfaSecret = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = strtolower(trim($email));

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_ADMIN';

        return array_values(array_unique($roles));
    }

    public function setRoles(array $roles): static
    {
        $this->roles = array_values(array_unique(array_map('strval', $roles)));

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getMfaSecret(): ?string
    {
        return $this->mfaSecret;
    }

    public function setMfaSecret(?string $mfaSecret): static
    {
        $mfaSecret = $mfaSecret !== null ? trim($mfaSecret) : null;
        $this->mfaSecret = $mfaSecret === '' ? null : $mfaSecret;

        return $this;
    }

    public function hasMfaSecret(): bool
    {
        return $this->mfaSecret !== null && $this->mfaSecret !== '';
    }

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->hasMfaSecret();
    }

    public function getTotpAuthenticationUsername(): ?string
    {
        return $this->getEmail();
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfigurationInterface
    {
        if (!$this->hasMfaSecret()) {
            return null;
        }

        return new TotpConfiguration(
            $this->mfaSecret,
            self::TOTP_ALGORITHM,
            self::TOTP_PERIOD,
            self::TOTP_DIGITS,
        );
    }
}
