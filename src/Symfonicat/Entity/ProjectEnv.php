<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(
    name: 'symfonicat_project_env',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_symfonicat_project_env_project_env', columns: ['project_id', 'env_id']),
    ],
)]
class ProjectEnv
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class, inversedBy: 'env')]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Env::class)]
    #[ORM\JoinColumn(name: 'env_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Env $env = null;

    #[ORM\Column(type: 'text')]
    private string $value = '';

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

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
