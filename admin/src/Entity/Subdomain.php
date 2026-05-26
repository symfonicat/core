<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\SubdomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SubdomainRepository::class)]
#[ORM\Table(name: 'symfonicat_subdomain')]
class Subdomain
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Parcel $parcel = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $catch = false;

    /**
     * @var Collection<int, Middleware>
     */
    #[ORM\ManyToMany(targetEntity: Middleware::class)]
    #[ORM\JoinTable(name: 'symfonicat_subdomain_middleware')]
    #[ORM\JoinColumn(name: 'subdomain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'middleware_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $middlewares;

    public function getParcel(): ?Parcel
    {
        return $this->parcel;
    }

    public function setParcel(?Parcel $parcel): static
    {
        $this->parcel = $parcel;

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
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, mappedBy: 'subdomains')]
    private Collection $domains;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, mappedBy: 'subdomains')]
    private Collection $modules;

    /**
     * @var Collection<int, SubdomainEnv>
     */
    #[ORM\OneToMany(targetEntity: SubdomainEnv::class, mappedBy: 'subdomain', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
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
        if (str_contains($id, '/')) {
            $id = substr($id, strrpos($id, '/') + 1);
        }

        if ($id === '') {
            throw new \InvalidArgumentException(sprintf('Subdomain id must be an unprefixed subdomain label, got "%s".', $id));
        }

        $this->id = $id;

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

            if (!$domain->hasSubdomain($this)) {
                $domain->getSubdomains()->add($this);
            }
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain) && $domain->hasSubdomain($this)) {
            $domain->getSubdomains()->removeElement($this);
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

            if (!$module->hasSubdomain($this)) {
                $module->getSubdomains()->add($this);
            }
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module) && $module->hasSubdomain($this)) {
            $module->getSubdomains()->removeElement($this);
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
     * @return Collection<int, SubdomainEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(SubdomainEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setSubdomain($this);
        }

        return $this;
    }

    public function removeEnv(SubdomainEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getSubdomain() === $this) {
            $env->setSubdomain(null);
        }

        return $this;
    }
}
