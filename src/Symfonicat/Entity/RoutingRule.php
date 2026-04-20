<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\RoutingRuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RoutingRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback('validateScope')]
#[ORM\Table(
    name: 'symfonicat_routing_rule',
    indexes: [
        new ORM\Index(name: 'symfonicat_routing_rule_domain_argument_idx', columns: ['type', 'domain_id', 'argument']),
        new ORM\Index(name: 'symfonicat_routing_rule_project_argument_idx', columns: ['type', 'project_id', 'argument']),
    ],
)]
class RoutingRule
{
    public const TYPE_DOMAIN = 'domain';
    public const TYPE_PROJECT = 'project';
    public const RESERVED_ARGUMENT = 'admin';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $argument = '';

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Domain $domain = null;

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_DOMAIN;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    public static function getTypeChoices(): array
    {
        return [
            'domain' => self::TYPE_DOMAIN,
            'project' => self::TYPE_PROJECT,
        ];
    }

    public static function getTypes(): array
    {
        return array_values(self::getTypeChoices());
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getArgument(): string
    {
        return $this->argument;
    }

    public function setArgument(string $argument): self
    {
        $this->argument = $argument;

        return $this;
    }

    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function setDomain(?Domain $domain): self
    {
        $this->domain = $domain;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::getTypes(), true)) {
            throw new \InvalidArgumentException(sprintf('Unsupported routing rule type "%s".', $type));
        }

        $this->type = $type;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function isDomainType(): bool
    {
        return $this->type === self::TYPE_DOMAIN;
    }

    public function isProjectType(): bool
    {
        return $this->type === self::TYPE_PROJECT;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function normalizeScope(): void
    {
        if ($this->isDomainType()) {
            $this->project = null;

            return;
        }

        if ($this->isProjectType()) {
            $this->domain = null;
        }
    }

    public function validateScope(ExecutionContextInterface $context): void
    {
        $this->validateArgument($context);

        if ($this->isDomainType() && $this->domain === null) {
            $context->buildViolation('A domain routing rule requires a domain.')
                ->atPath('domain')
                ->addViolation();
        }

        if ($this->isProjectType() && $this->project === null) {
            $context->buildViolation('A project routing rule requires a project.')
                ->atPath('project')
                ->addViolation();
        }
    }

    public function validateArgument(ExecutionContextInterface $context): void
    {
        if (strtolower(trim($this->argument)) === self::RESERVED_ARGUMENT) {
            $context->buildViolation(sprintf('The routing rule argument "%s" is reserved.', self::RESERVED_ARGUMENT))
                ->atPath('argument')
                ->addViolation();
        }
    }
}
