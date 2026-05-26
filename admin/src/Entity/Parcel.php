<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\ParcelRepository;

#[ORM\Entity(repositoryClass: ParcelRepository::class)]
#[ORM\Table(name: 'symfonicat_parcel')]
class Parcel
{
    use VendorScopedIdTrait;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(length: 1024)]
    private string $path = '';

    /**
     * @var Collection<int, ParcelEnv>
     */
    #[ORM\OneToMany(targetEntity: ParcelEnv::class, mappedBy: 'parcel', cascade: ['persist', 'remove'], orphanRemoval: true)]
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
     * @return Collection<int, ParcelEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(ParcelEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setParcel($this);
        }

        return $this;
    }

    public function removeEnv(ParcelEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getParcel() === $this) {
            $env->setParcel(null);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getId() ?? '';
    }
}
