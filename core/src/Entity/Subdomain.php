<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\SubdomainRepository;

#[ORM\Entity(repositoryClass: SubdomainRepository::class)]
#[ORM\Table(name: 'symfonicat_subdomain')]
#[ORM\UniqueConstraint(name: 'uniq_symfonicat_subdomain_domain_affix', columns: ['domain_id', 'affix'])]
class Subdomain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $affix = '';

    #[ORM\ManyToOne(targetEntity: Domain::class, inversedBy: 'subdomains')]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Domain $domain = null;

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
        $this->modules = new ArrayCollection();
        $this->env = new ArrayCollection();
        $this->middlewares = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAffix(): ?string
    {
        $affix = trim($this->affix);

        return $affix === '' ? null : $affix;
    }

    public function setAffix(string $affix): static
    {
        $affix = trim($affix, " \t\n\r\0\x0B/");
        if ($affix === '') {
            throw new \InvalidArgumentException('Subdomain affix must be non-empty.');
        }

        if (str_contains($affix, '/')) {
            $affix = substr($affix, strrpos($affix, '/') + 1);
        }

        $this->affix = $affix;

        return $this;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): static
    {
        if ($this->domain === $domain) {
            return $this;
        }

        $previousDomain = $this->domain;
        $this->domain = $domain;

        if ($previousDomain instanceof Domain) {
            $previousDomain->getSubdomains()->removeElement($this);
        }

        if ($domain instanceof Domain && !$domain->getSubdomains()->contains($this)) {
            $domain->getSubdomains()->add($this);
        }

        return $this;
    }

    public function hasDomain(Domain $domain): bool
    {
        return $this->domain === $domain
            || ($this->domain instanceof Domain && $domain->getTld() !== null && $this->domain->getTld() === $domain->getTld());
    }

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
