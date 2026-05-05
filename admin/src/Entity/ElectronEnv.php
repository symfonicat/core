<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'symfonicat_electron_env',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_symfonicat_electron_env_electron_env', columns: ['electron_id', 'env_id']),
    ],
)]
class ElectronEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Electron::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'electron_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Electron $electron = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getElectron(): ?Electron
    {
        return $this->electron;
    }

    public function setElectron(?Electron $electron): static
    {
        $this->electron = $electron;

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
