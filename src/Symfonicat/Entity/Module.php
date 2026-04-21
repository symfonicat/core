<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\ModuleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModuleRepository::class)]
#[ORM\Table(name: 'symfonicat_module')]
class Module
{
    #[ORM\Id]
    #[ORM\Column(length: 50)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_project')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $projects;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_domain')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $domains;

    /**
     * @var Collection<int, Application>
     */
    #[ORM\ManyToMany(targetEntity: Application::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_application')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'application_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $applications;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->domains = new ArrayCollection();
        $this->applications = new ArrayCollection();
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

    public function setName(string $name): static
    {
        $this->name = $name;

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

            if (!$project->hasModule($this)) {
                $project->getModules()->add($this);
            }
        }

        return $this;
    }

    public function removeProject(Project $project): static
    {
        if ($this->projects->removeElement($project) && $project->hasModule($this)) {
            $project->getModules()->removeElement($this);
        }

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

            if (!$domain->hasModule($this)) {
                $domain->getModules()->add($this);
            }
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain) && $domain->hasModule($this)) {
            $domain->getModules()->removeElement($this);
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

    /**
     * @return Collection<int, Application>
     */
    public function getApplications(): Collection
    {
        return $this->applications;
    }

    public function addApplication(Application $application): static
    {
        if (!$this->hasApplication($application)) {
            $this->applications->add($application);

            if (!$application->hasModule($this)) {
                $application->getModules()->add($this);
            }
        }

        return $this;
    }

    public function removeApplication(Application $application): static
    {
        if ($this->applications->removeElement($application) && $application->hasModule($this)) {
            $application->getModules()->removeElement($this);
        }

        return $this;
    }

    public function hasApplication(Application $application): bool
    {
        foreach ($this->applications as $existingApplication) {
            if ($existingApplication === $application) {
                return true;
            }

            if ($existingApplication->getId() !== null && $application->getId() !== null && $existingApplication->getId() === $application->getId()) {
                return true;
            }
        }

        return false;
    }
}
