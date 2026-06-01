<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'symfonicat_subdomain_env')]
#[ORM\UniqueConstraint(name: 'uniq_symfonicat_subdomain_env_subdomain_env', columns: ['subdomain_id', 'env_id'])]
class SubdomainEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Subdomain::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'subdomain_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Subdomain $subdomain = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSubdomain(): ?Subdomain
    {
        return $this->subdomain;
    }

    public function setSubdomain(?Subdomain $subdomain): static
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    public function getEnv(): ?Env
    {
        return $this->env;
    }

    public function setEnv(?Env $env): static
    {
        $this->env = $env;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = trim((string) $value);

        return $this;
    }
}
