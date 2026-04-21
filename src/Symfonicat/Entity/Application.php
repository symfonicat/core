<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\ApplicationRepository;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'symfonicat_application')]
class Application
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $id = null;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, mappedBy: 'applications')]
    private Collection $modules;

    /**
     * @var Collection<int, ApplicationEnv>
     */
    #[ORM\OneToMany(targetEntity: ApplicationEnv::class, mappedBy: 'application', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->env = new ArrayCollection();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return Collection<int, Module>
     */
    public function getModules(): Collection
    {
        return $this->modules;
    }

    public function addModule(Module $module): static
    {
        if (!$this->hasModule($module)) {
            $this->modules->add($module);

            if (!$module->hasApplication($this)) {
                $module->getApplications()->add($this);
            }
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module) && $module->hasApplication($this)) {
            $module->getApplications()->removeElement($this);
        }

        return $this;
    }

    public function hasModule(Module $module): bool
    {
        foreach ($this->modules as $existingModule) {
            if ($existingModule === $module) {
                return true;
            }

            if ($existingModule->getId() !== null && $module->getId() !== null && $existingModule->getId() === $module->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, ApplicationEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(ApplicationEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setApplication($this);
        }

        return $this;
    }

    public function removeEnv(ApplicationEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getApplication() === $this) {
            $env->setApplication(null);
        }

        return $this;
    }
}
