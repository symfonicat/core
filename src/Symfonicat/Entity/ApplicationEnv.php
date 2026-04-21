<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'symfonicat_application_env',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_symfonicat_application_env_application_env', columns: ['application_id', 'env_id']),
    ],
)]
class ApplicationEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): static
    {
        $this->application = $application;

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
