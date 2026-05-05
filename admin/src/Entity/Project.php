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
    use VendorScopedIdTrait;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;



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

            if ($existingDomain->getId(true) !== null && $domain->getId(true) !== null && $existingDomain->getId(true) === $domain->getId(true)) {
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

            if ($existingModule->getId(true) !== null && $module->getId(true) !== null && $existingModule->getId(true) === $module->getId(true)) {
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
