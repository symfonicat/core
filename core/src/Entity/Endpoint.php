<?php

namespace Symfonicat\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfonicat\Repository\EndpointRepository;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;

#[ORM\Entity(repositoryClass: EndpointRepository::class)]
#[ORM\Table(name: 'symfonicat_endpoint')]
class Endpoint
{
    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Parcel::class)]
    #[ORM\JoinColumn(name: 'parcel_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Parcel $parcel = null;

    public const ENFORCE_DOMAIN = 'domain';
    public const ENFORCE_SUBDOMAIN = 'subdomain';

    #[ORM\Column(length: 16, options: ['default' => null], nullable: true, name: 'enforce')]
    private ?string $enforce = null;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Subdomain::class)]
    #[ORM\JoinColumn(name: 'subdomain_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Subdomain $subdomain = null;

    /**
     * @var Collection<int, Module>
     */
    #[ORM\ManyToMany(targetEntity: Module::class, mappedBy: 'endpoints')]
    private Collection $modules;

    /**
     * @var Collection<int, Middleware>
     */
    #[ORM\ManyToMany(targetEntity: Middleware::class)]
    #[ORM\JoinTable(name: 'symfonicat_endpoint_middleware')]
    #[ORM\JoinColumn(name: 'endpoint_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\InverseJoinColumn(name: 'middleware_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $middlewares;

    /**
     * @var Collection<int, EndpointEnv>
     */
    #[ORM\OneToMany(targetEntity: EndpointEnv::class, mappedBy: 'endpoint', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    #[ORM\Column(options: ['default' => false])]
    private bool $catch = false;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $arguments = [];

    public function __construct()
    {
        $this->modules = new ArrayCollection();
        $this->middlewares = new ArrayCollection();
        $this->env = new ArrayCollection();
    }

    public function getId(bool $includeVendor = true): ?string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $id = trim($id, " \t\n\r\0\x0B/");
        if ($id === '') {
            throw new \InvalidArgumentException('Endpoint id must be non-empty.');
        }

        $this->id = $id;

        return $this;
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

            if (!$module->hasEndpoint($this)) {
                $module->getEndpoints()->add($this);
            }
        }

        return $this;
    }

    public function removeModule(Module $module): static
    {
        if ($this->modules->removeElement($module) && $module->hasEndpoint($this)) {
            $module->getEndpoints()->removeElement($this);
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
     * @return Collection<int, EndpointEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(EndpointEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setEndpoint($this);
        }

        return $this;
    }

    public function removeEnv(EndpointEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getEndpoint() === $this) {
            $env->setEndpoint(null);
        }

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
     * @return list<string>
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @param iterable<mixed>|null $arguments
     */
    public function setArguments(?iterable $arguments): static
    {
        $normalizedArguments = [];

        foreach ($arguments ?? [] as $argument) {
            $argument = trim((string) $argument, " \t\n\r\0\x0B/");
            if ($argument === '') {
                continue;
            }

            $normalizedArguments[] = $argument;
        }

        $this->arguments = array_values($normalizedArguments);

        return $this;
    }

    public function getArgumentsPath(): string
    {
        if ($this->arguments === []) {
            return '';
        }

        return '/'.implode('/', $this->arguments);
    }

    public function getEnforce(): ?string
    {
        return $this->enforce;
    }

    public function setEnforce(?string $enforce): static
    {
        if ($enforce === null) {
            $this->enforce = null;

            return $this;
        }

        $normalized = trim((string) $enforce);
        if ($normalized === '') {
            $this->enforce = null;

            return $this;
        }

        $this->enforce = strtolower($normalized);

        return $this;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getSubdomain(): ?Subdomain
    {
        return $this->subdomain;
    }

    public function setSubdomain(?Subdomain $subdomain): static
    {
        $this->subdomain = $subdomain;

        return $this;
    }

    public function __toString(): string
    {
        return $this->getId() ?? '';
    }
}
