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
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    /**
     * @var Collection<int, Project>
     */
    #[ORM\ManyToMany(targetEntity: Project::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_project')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'project_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $projects;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_domain')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $domains;

    public function __construct()
    {
        $this->projects = new ArrayCollection();
        $this->domains = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

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
}
