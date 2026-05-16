<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'symfonicat_parcel_env')]
#[ORM\UniqueConstraint(name: 'uniq_symfonicat_parcel_env_parcel_env', columns: ['parcel_id', 'env_id'])]
class ParcelEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Parcel::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Parcel $parcel = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParcel(): ?Parcel
    {
        return $this->parcel;
    }

    public function setParcel(?Parcel $parcel): static
    {
        $this->parcel = $parcel;

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
