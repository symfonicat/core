<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfonicat\Repository\ElectronRepository;

#[ORM\Entity(repositoryClass: ElectronRepository::class)]
#[ORM\Table(name: 'symfonicat_electron')]
class Electron
{
    public const TYPE_DOMAIN = 'domain';
    public const TYPE_PROJECT = 'project';
    public const TYPE_APPLICATION = 'application';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private string $type = self::TYPE_DOMAIN;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(name: 'domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'project_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $favicon = null;

    public static function typeChoices(): array
    {
        return [
            'domain' => self::TYPE_DOMAIN,
            'project' => self::TYPE_PROJECT,
            'application' => self::TYPE_APPLICATION,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): static
    {
        $this->application = $application;

        return $this;
    }

    public function getFavicon(): ?string
    {
        return $this->favicon;
    }

    public function setFavicon(?string $favicon): static
    {
        $this->favicon = $favicon;

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

    public function isApplicationType(): bool
    {
        return $this->type === self::TYPE_APPLICATION;
    }

    public function getTargetId(): ?string
    {
        return match ($this->type) {
            self::TYPE_DOMAIN => $this->domain?->getId(),
            self::TYPE_PROJECT => $this->project?->getId(),
            self::TYPE_APPLICATION => $this->application?->getId(),
            default => null,
        };
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        $type = trim($this->type);

        if (!in_array($type, self::typeChoices(), true)) {
            $context->buildViolation('Choose a valid Electron type.')
                ->atPath('type')
                ->addViolation();

            return;
        }

        if ($type === self::TYPE_DOMAIN && !$this->domain instanceof Domain) {
            $context->buildViolation('Select a domain.')
                ->atPath('domain')
                ->addViolation();
        }

        if ($type === self::TYPE_PROJECT && !$this->project instanceof Project) {
            $context->buildViolation('Select a project.')
                ->atPath('project')
                ->addViolation();
        }

        if ($type === self::TYPE_APPLICATION && !$this->application instanceof Application) {
            $context->buildViolation('Select an application.')
                ->atPath('application')
                ->addViolation();
        }
    }
}
