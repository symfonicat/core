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
    use VendorScopedIdTrait;

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $package = null;

    /**
     * @var Collection<int, Subdomain>
     */
    #[ORM\ManyToMany(targetEntity: Subdomain::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_subdomain')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'subdomain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $subdomains;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_domain')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'domain_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $domains;

    /**
     * @var Collection<int, Endpoint>
     */
    #[ORM\ManyToMany(targetEntity: Endpoint::class, inversedBy: 'modules')]
    #[ORM\JoinTable(name: 'symfonicat_module_endpoint')]
    #[ORM\JoinColumn(name: 'module_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'endpoint_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private Collection $endpoints;

    public function __construct()
    {
        $this->subdomains = new ArrayCollection();
        $this->domains = new ArrayCollection();
        $this->endpoints = new ArrayCollection();
    }


    public function getPackage(): ?string
    {
        return $this->package;
    }

    public function setPackage(?string $package): static
    {
        $package = $package === null ? null : trim($package);
        $this->package = $package === '' ? null : $package;

        return $this;
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

            if (!$subdomain->hasModule($this)) {
                $subdomain->getModules()->add($this);
            }
        }

        return $this;
    }

    public function removeSubdomain(Subdomain $subdomain): static
    {
        if ($this->subdomains->removeElement($subdomain) && $subdomain->hasModule($this)) {
            $subdomain->getModules()->removeElement($this);
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

    /**
     * @return Collection<int, Endpoint>
     */
    public function getEndpoints(): Collection
    {
        return $this->endpoints;
    }

    public function addEndpoint(Endpoint $endpoint): static
    {
        if (!$this->hasEndpoint($endpoint)) {
            $this->endpoints->add($endpoint);

            if (!$endpoint->hasModule($this)) {
                $endpoint->getModules()->add($this);
            }
        }

        return $this;
    }

    public function removeEndpoint(Endpoint $endpoint): static
    {
        if ($this->endpoints->removeElement($endpoint) && $endpoint->hasModule($this)) {
            $endpoint->getModules()->removeElement($this);
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

            if (
                $existingSubdomain->getAffix() !== null
                && $subdomain->getAffix() !== null
                && $existingSubdomain->getAffix() === $subdomain->getAffix()
                && $existingSubdomain->getDomain()?->getId() === $subdomain->getDomain()?->getId()
            ) {
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

    public function hasEndpoint(Endpoint $endpoint): bool
    {
        foreach ($this->endpoints as $existingEndpoint) {
            if ($existingEndpoint === $endpoint) {
                return true;
            }

            if ($existingEndpoint->getId() !== null && $endpoint->getId() !== null && $existingEndpoint->getId() === $endpoint->getId()) {
                return true;
            }
        }

        return false;
    }
}
