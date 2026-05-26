<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'symfonicat_endpoint_env')]
#[ORM\UniqueConstraint(name: 'uniq_symfonicat_endpoint_env_endpoint_env', columns: ['endpoint_id', 'env_id'])]
class EndpointEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Endpoint::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'endpoint_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Endpoint $endpoint = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): static
    {
        $this->endpoint = $endpoint;

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
