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

    #[ORM\ManyToOne(targetEntity: Bundle::class)]
    #[ORM\JoinColumn(name: 'bundle_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Bundle $bundle = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $catch = false;

    /**
     * @var Collection<int, Middleware>
     */
    #[ORM\ManyToMany(targetEntity: Middleware::class)]
    #[ORM\JoinTable(name: 'symfonicat_domain_middleware')]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'middleware_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $middlewares;

    /**
     * @var Collection<int, Subdomain>
     */
    #[ORM\ManyToMany(targetEntity: Subdomain::class, inversedBy: 'domains')]
    #[ORM\JoinTable(name: 'symfonicat_domain_subdomain')]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'subdomain_id', referencedColumnName: 'id')]
    private Collection $subdomains;

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
        $this->subdomains = new ArrayCollection();
        $this->modules = new ArrayCollection();
        $this->env = new ArrayCollection();
        $this->middlewares = new ArrayCollection();
    }

    public function getId(bool $includeVendor = true): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $id = trim($id, " \t\n\r\0\x0B/");
        if ($id === '' || str_contains($id, '/')) {
            throw new \InvalidArgumentException(sprintf('Domain id must be a bare domain name, got "%s".', $id));
        }

        $this->id = $id;

        return $this;
    }

    public function getBundle(): ?Bundle
    {
        return $this->bundle;
    }

    public function setBundle(?Bundle $bundle): static
    {
        $this->bundle = $bundle;

        return $this;
    }

    public function isCatch(): bool
    {
        return $this->catch;
    }

    public function setCatch(bool $catch): static
    {
        $this->catch = $catch;

        return $this;
    }

    /**
     * @return Collection<int, Middleware>
     */
    public function getMiddlewares(): Collection
    {
        return $this->middlewares;
    }

    public function addMiddleware(Middleware $middleware): static
    {
        if (!$this->hasMiddleware($middleware)) {
            $this->middlewares->add($middleware);
        }

        return $this;
    }

    public function removeMiddleware(Middleware $middleware): static
    {
        $this->middlewares->removeElement($middleware);

        return $this;
    }

    public function hasMiddleware(Middleware $middleware): bool
    {
        foreach ($this->middlewares as $existingMiddleware) {
            if ($existingMiddleware === $middleware) {
                return true;
            }

            if ($existingMiddleware->getId() !== null && $middleware->getId() !== null && $existingMiddleware->getId() === $middleware->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, Subdomain>
     */
    public function getSubdomains(): Collection
    {
        return $this->subdomains;
    }

    public function addSubdomain(Subdomain $subdomain): static
    {
        if (!$this->hasSubdomain($subdomain)) {
            $this->subdomains->add($subdomain);

            if (!$subdomain->hasDomain($this)) {
                $subdomain->getDomains()->add($this);
            }
        }

        return $this;
    }

    public function removeSubdomain(Subdomain $subdomain): static
    {
        if ($this->subdomains->removeElement($subdomain) && $subdomain->hasDomain($this)) {
            $subdomain->getDomains()->removeElement($this);
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

    public function hasSubdomain(Subdomain $subdomain): bool
    {
        foreach ($this->subdomains as $existingSubdomain) {
            if ($existingSubdomain === $subdomain) {
                return true;
            }

            if ($existingSubdomain->getId() !== null && $subdomain->getId() !== null && $existingSubdomain->getId() === $subdomain->getId()) {
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
