<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
#[ORM\Table(name: 'symfonicat_project')]
class Project
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $icon = null;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, mappedBy: 'projects')]
    private Collection $domains;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, mappedBy: 'projects')]
    private Collection $modules;

    /**
     * @var Collection<int, ProjectEnv>
     */
    #[ORM\OneToMany(targetEntity: ProjectEnv::class, mappedBy: 'project', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;

        return $this;
    }

    /**
     * @return Collection<int, Domain>
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(Domain $domain): static
    {
        if (!$this->hasDomain($domain)) {
            $this->domains->add($domain);

            if (!$domain->hasProject($this)) {
                $domain->getProjects()->add($this);
            }
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain) && $domain->hasProject($this)) {
            $domain->getProjects()->removeElement($this);
        }

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

            if (!$module->hasProject($this)) {
                $module->getProjects()->add($this);
            }
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module) && $module->hasProject($this)) {
            $module->getProjects()->removeElement($this);
        }

        return $this;
    }

    public function hasDomain(Domain $domain): bool
    {
        foreach ($this->domains as $existingDomain) {
            if ($existingDomain === $domain) {
                return true;
            }

            if ($existingDomain->getId() !== null && $domain->getId() !== null && $existingDomain->getId() === $domain->getId()) {
                return true;
            }
        }

        return false;
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
     * @return Collection<int, ProjectEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(ProjectEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setProject($this);
        }

        return $this;
    }

    public function removeEnv(ProjectEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getProject() === $this) {
            $env->setProject(null);
        }

        return $this;
    }
}
