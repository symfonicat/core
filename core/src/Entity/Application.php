<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfonicat\Repository\ApplicationRepository;

#[ORM\Entity(repositoryClass: ApplicationRepository::class)]
#[ORM\Table(name: 'symfonicat_application')]
class Application
{
    public const TYPE_DOMAIN = 'domain';
    public const TYPE_SUBDOMAIN = 'subdomain';
    public const TYPE_ENDPOINT = 'endpoint';

    #[ORM\Id]
    #[ORM\Column(length: 255)]
    private ?string $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_DOMAIN;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Subdomain::class)]
    #[ORM\JoinColumn(name: 'subdomain_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Subdomain $subdomain = null;

    #[ORM\ManyToOne(targetEntity: Endpoint::class)]
    #[ORM\JoinColumn(name: 'endpoint_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Endpoint $endpoint = null;

    /**
     * @var Collection<int, ApplicationEnv>
     */
    #[ORM\OneToMany(targetEntity: ApplicationEnv::class, mappedBy: 'application', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $env;

    public function __construct()
    {
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
            throw new \InvalidArgumentException('Application id must be non-empty.');
        }

        $this->id = $id;

        return $this;
    }

    public static function typeChoices(): array
    {
        return [
            'domain' => self::TYPE_DOMAIN,
            'subdomain' => self::TYPE_SUBDOMAIN,
            'endpoint' => self::TYPE_ENDPOINT,
        ];
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

    public function getType(): string
    {
        if ($this->endpoint instanceof Endpoint) {
            return self::TYPE_ENDPOINT;
        }

        if ($this->subdomain instanceof Subdomain) {
            return self::TYPE_SUBDOMAIN;
        }

        if ($this->domain instanceof Domain) {
            return self::TYPE_DOMAIN;
        }

        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getEndpoint(): ?Endpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(?Endpoint $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    /**
     * @return Collection<int, ApplicationEnv>
     */
    public function getEnv(): Collection
    {
        return $this->env;
    }

    public function addEnv(ApplicationEnv $env): static
    {
        if (!$this->env->contains($env)) {
            $this->env->add($env);
            $env->setApplication($this);
        }

        return $this;
    }

    public function removeEnv(ApplicationEnv $env): static
    {
        if ($this->env->removeElement($env) && $env->getApplication() === $this) {
            $env->setApplication(null);
        }

        return $this;
    }

    public function isDomainType(): bool
    {
        return $this->getType() === self::TYPE_DOMAIN;
    }

    public function isSubdomainType(): bool
    {
        return $this->getType() === self::TYPE_SUBDOMAIN;
    }

    public function isEndpointType(): bool
    {
        return $this->getType() === self::TYPE_ENDPOINT;
    }

    public function getTargetId(bool $includeVendor = false): ?string
    {
        return match ($this->getType()) {
            self::TYPE_DOMAIN => $this->domain?->getId($includeVendor),
            self::TYPE_SUBDOMAIN => $this->subdomainTargetId($includeVendor),
            self::TYPE_ENDPOINT => $this->endpoint?->getId($includeVendor),
            default => null,
        };
    }

    public function subdomainTargetId(bool $includeVendor = false): ?string
    {
        $subdomainAffix = trim((string) $this->subdomain?->getAffix());
        if ($subdomainAffix === '') {
            return null;
        }

        $domainId = trim((string) $this->domain?->getId($includeVendor));
        if ($domainId === '') {
            return null;
        }

        return sprintf('%s.%s', $subdomainAffix, $domainId);
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (!$this->domain instanceof Domain) {
            $context->buildViolation('Select a domain.')
                ->atPath('domain')
                ->addViolation();
        }
    }
}
