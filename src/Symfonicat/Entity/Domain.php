<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\DomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainRepository::class)]
#[ORM\Table(name: 'symfonicat_domain')]
class Domain
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $routeOverride = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $routeName = null;

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'redirect_domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $redirectDomain = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'domains')]
    #[ORM\JoinTable(name: 'symfonicat_domain_project')]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'project_id', referencedColumnName: 'id')]
    private Collection $projects;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, mappedBy: 'domains')]
    private Collection $modules;

    /**
     * @var Collection<int, DomainEnv>
     */
    #[ORM\OneToMany(targetEntity: DomainEnv::class, mappedBy: 'domain', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
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

    public function isRouteOverride(): bool
    {
        return $this->routeOverride;
    }

    public function getRouteOverride(): bool
    {
        return $this->routeOverride;
    }

    public function setRouteOverride(bool $routeOverride): static
    {
        $this->routeOverride = $routeOverride;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): static
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function getRedirectDomain(): ?self
    {
        return $this->redirectDomain;
    }

    public function setRedirectDomain(?self $redirectDomain): static
    {
        $this->redirectDomain = $redirectDomain;

        return $this;
    }

    /**
     * @return Collection<int, Project>
     */
    public function getProjects(): Collection
    {
        return $this->projects;
    }

    public function addProject(Project $project): static
    {
        if (!$this->hasProject($project)) {
            $this->projects->add($project);

            if (!$project->hasDomain($this)) {
                $project->getDomains()->add($this);
            }
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project) && $project->hasDomain($this)) {
            $project->getDomains()->removeElement($this);
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

            if (!$module->hasDomain($this)) {
                $module->getDomains()->add($this);
            }
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module) && $module->hasDomain($this)) {
            $module->getDomains()->removeElement($this);
        }

        return $this;
    }

    public function hasProject(Project $project): bool
    {
        foreach ($this->projects as $existingProject) {
            if ($existingProject === $project) {
                return true;
            }

            if ($existingProject->getId() !== null && $project->getId() !== null && $existingProject->getId() === $project->getId()) {
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
     * @return Collection<int, DomainEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(DomainEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setDomain($this);
        }

        return $this;
    }

    public function removeEnv(DomainEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getDomain() === $this) {
            $env->setDomain(null);
        }

        return $this;
    }
}
