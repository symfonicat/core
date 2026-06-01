<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\EnvParentRepository;

#[ORM\Entity(repositoryClass: EnvParentRepository::class)]
#[ORM\Table(name: 'symfonicat_env_parent')]
class EnvParent
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    /**
     * @var Collection<int, Env>
     */
    #[ORM\OneToMany(targetEntity: Env::class, mappedBy: 'envParent')]
    private Collection $env;

    public function __construct()
    {
        $this->env = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = trim($id);

        return $this;
    }

    /**
     * @return Collection<int, Env>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function __toString(): string
    {
        return $this->id ?? '';
    }
}
