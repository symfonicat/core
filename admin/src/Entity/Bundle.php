<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\BundleRepository;

#[ORM\Entity(repositoryClass: BundleRepository::class)]
#[ORM\Table(name: 'symfonicat_bundle')]
class Bundle
{
    use VendorScopedIdTrait;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(length: 1024)]
    private string $path = '';

    /**
     * @var Collection<int, BundleEnv>
     */
    #[ORM\OneToMany(targetEntity: BundleEnv::class, mappedBy: 'bundle', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
        $this->env = new ArrayCollection();
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(?string $path): static
    {
        $this->path = trim((string) $path);

        return $this;
    }

    /**
     * @return Collection<int, BundleEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(BundleEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setBundle($this);
        }

        return $this;
    }

    public function removeEnv(BundleEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getBundle() === $this) {
            $env->setBundle(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getId() ?? '';
    }
}
