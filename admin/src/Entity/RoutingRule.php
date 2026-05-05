<?php

namespace Symfonicat\Entity;

use Symfonicat\Repository\RoutingRuleRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: RoutingRuleRepository::class)]
#[ORM\HasLifecycleCallbacks]
#[Assert\Callback('validateScope')]
#[ORM\Table(name: 'symfonicat_routing_rule')]
#[ORM\Index(name: 'symfonicat_routing_rule_domain_arguments_idx', columns: ['type', 'redirect_type', 'route_type', 'domain_id'])]
#[ORM\Index(name: 'symfonicat_routing_rule_project_arguments_idx', columns: ['type', 'redirect_type', 'route_type', 'project_id'])]
#[ORM\Index(name: 'symfonicat_routing_rule_application_arguments_idx', columns: ['type', 'application_type', 'application_id', 'route'])]
class RoutingRule
{
    public const TYPE_DOMAIN = 'domain';
    public const TYPE_PROJECT = 'project';
    public const TYPE_APPLICATION = 'application';
    public const TYPE_REDIRECT = 'redirect';
    public const TYPE_ROUTE = 'route';

    public const REDIRECT_TYPE_DOMAIN = 'domain';
    public const REDIRECT_TYPE_PROJECT = 'project';
    public const REDIRECT_TYPE_DOMAIN_PROJECT = 'domain_project';

    public const TARGET_TYPE_DOMAIN = 'domain';
    public const TARGET_TYPE_PROJECT = 'project';

    public const ROUTE_TYPE_DOMAIN = 'domain';
    public const ROUTE_TYPE_PROJECT = 'project';

    public const APPLICATION_TYPE_ARGUMENTS = 'arguments';
    public const APPLICATION_TYPE_ROUTE = 'route';

    public const RESERVED_ARGUMENTS = [
        'admin',
        'm',
        'application',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: 'json')]
    private array $arguments = [];

    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_DOMAIN;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $redirectType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $redirectTarget = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $routeType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $applicationType = self::APPLICATION_TYPE_ARGUMENTS;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Domain $domain = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Project $project = null;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: Domain::class)]
    #[ORM\JoinColumn(name: 'redirect_domain_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Domain $redirectDomain = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(name: 'redirect_project_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Project $redirectProject = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    public static function getTypeChoices(): array
    {
        return [
            'domain' => self::TYPE_DOMAIN,
            'project' => self::TYPE_PROJECT,
            'application' => self::TYPE_APPLICATION,
            'redirect' => self::TYPE_REDIRECT,
            'route' => self::TYPE_ROUTE,
        ];
    }

    public static function getTypes(): array
    {
        return array_values(self::getTypeChoices());
    }

    public static function getRedirectTypeChoices(): array
    {
        return [
            'domain' => self::REDIRECT_TYPE_DOMAIN,
            'project' => self::REDIRECT_TYPE_PROJECT,
        ];
    }

    public static function getRedirectTargetChoices(): array
    {
        return [
            'domain' => self::TARGET_TYPE_DOMAIN,
            'project' => self::TARGET_TYPE_PROJECT,
            'domain and project' => self::REDIRECT_TYPE_DOMAIN_PROJECT,
        ];
    }

    public static function getRouteTypeChoices(): array
    {
        return [
            'domain' => self::ROUTE_TYPE_DOMAIN,
            'project' => self::ROUTE_TYPE_PROJECT,
        ];
    }

    public static function getApplicationTypeChoices(): array
    {
        return [
            'arguments' => self::APPLICATION_TYPE_ARGUMENTS,
            'route' => self::APPLICATION_TYPE_ROUTE,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getRedirectType(): ?string
    {
        return $this->redirectType;
    }

    public function setRedirectType(?string $redirectType): self
    {
        $this->redirectType = $redirectType;

        return $this;
    }

    public function getRedirectTarget(): ?string
    {
        return $this->redirectTarget;
    }

    public function setRedirectTarget(?string $redirectTarget): self
    {
        $this->redirectTarget = $redirectTarget;

        return $this;
    }

    public function getRouteType(): ?string
    {
        return $this->routeType;
    }

    public function setRouteType(?string $routeType): self
    {
        $this->routeType = $routeType;

        return $this;
    }

    public function getApplicationType(): string
    {
        return $this->applicationType ?? self::APPLICATION_TYPE_ARGUMENTS;
    }

    public function setApplicationType(?string $applicationType): self
    {
        $this->applicationType = $applicationType;

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

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): self
    {
        $this->project = $project;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

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
    public function setArguments(?iterable $arguments): self
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

    public function getRedirectDomain(): ?Domain
    {
        return $this->redirectDomain;
    }

    public function setRedirectDomain(?Domain $redirectDomain): self
    {
        $this->redirectDomain = $redirectDomain;

        return $this;
    }

    public function getRedirectProject(): ?Project
    {
        return $this->redirectProject;
    }

    public function setRedirectProject(?Project $redirectProject): self
    {
        $this->redirectProject = $redirectProject;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function isRedirectRule(): bool
    {
        return $this->type === self::TYPE_REDIRECT;
    }

    public function isRouteRule(): bool
    {
        return $this->type === self::TYPE_ROUTE;
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

    public function isApplicationArgumentsType(): bool
    {
        return $this->getApplicationType() === self::APPLICATION_TYPE_ARGUMENTS;
    }

    public function isApplicationRouteType(): bool
    {
        return $this->getApplicationType() === self::APPLICATION_TYPE_ROUTE;
    }

    public function isDomainRedirectType(): bool
    {
        return $this->redirectType === self::REDIRECT_TYPE_DOMAIN;
    }

    public function isProjectRedirectType(): bool
    {
        return $this->redirectType === self::REDIRECT_TYPE_PROJECT;
    }

    public function isDomainRedirectTarget(): bool
    {
        return $this->redirectTarget === self::TARGET_TYPE_DOMAIN;
    }

    public function isProjectRedirectTarget(): bool
    {
        return $this->redirectTarget === self::TARGET_TYPE_PROJECT;
    }

    public function isDomainAndProjectRedirectTarget(): bool
    {
        return $this->redirectTarget === self::REDIRECT_TYPE_DOMAIN_PROJECT;
    }

    public function isDomainRouteType(): bool
    {
        return $this->routeType === self::ROUTE_TYPE_DOMAIN;
    }

    public function isProjectRouteType(): bool
    {
        return $this->routeType === self::ROUTE_TYPE_PROJECT;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function normalizeScope(): void
    {
        if ($this->isDomainType()) {
            $this->project = null;
            $this->application = null;
            $this->redirectType = null;
            $this->redirectTarget = null;
            $this->routeType = null;
            $this->applicationType = null;
            $this->redirectDomain = null;
            $this->redirectProject = null;
            $this->route = null;

            return;
        }

        if ($this->isProjectType()) {
            $this->domain = null;
            $this->application = null;
            $this->redirectType = null;
            $this->redirectTarget = null;
            $this->routeType = null;
            $this->applicationType = null;
            $this->redirectDomain = null;
            $this->redirectProject = null;
            $this->route = null;

            return;
        }

        if ($this->isApplicationType()) {
            $this->domain = null;
            $this->project = null;
            $this->redirectType = null;
            $this->redirectTarget = null;
            $this->routeType = null;
            $this->redirectDomain = null;
            $this->redirectProject = null;

            if ($this->isApplicationRouteType()) {
                $this->arguments = [];
            } else {
                $this->route = null;
                $this->applicationType = self::APPLICATION_TYPE_ARGUMENTS;
            }

            return;
        }

        if ($this->isRedirectRule()) {
            $this->arguments = [];
            $this->application = null;
            $this->routeType = null;
            $this->applicationType = null;
            $this->route = null;

            if ($this->isDomainRedirectType()) {
                $this->project = null;
            } elseif ($this->isProjectRedirectType()) {
                $this->domain = null;
            } else {
                $this->domain = null;
                $this->project = null;
            }

            if ($this->isDomainRedirectTarget()) {
                $this->redirectProject = null;
            } elseif ($this->isProjectRedirectTarget()) {
                $this->redirectDomain = null;
            } elseif ($this->isDomainAndProjectRedirectTarget()) {
                // Combined redirects keep both the explicit domain and project.
            } else {
                $this->redirectDomain = null;
                $this->redirectProject = null;
            }

            return;
        }

        if ($this->isRouteRule()) {
            $this->arguments = [];
            $this->application = null;
            $this->redirectType = null;
            $this->redirectTarget = null;
            $this->applicationType = null;
            $this->redirectDomain = null;
            $this->redirectProject = null;

            if ($this->isDomainRouteType()) {
                $this->project = null;
            } elseif ($this->isProjectRouteType()) {
                $this->domain = null;
            } else {
                $this->domain = null;
                $this->project = null;
            }
        }
    }

    public function validateScope(ExecutionContextInterface $context): void
    {
        if ($this->isDomainType()) {
            $this->validateArguments($context);

            if ($this->domain === null) {
                $context->buildViolation('A domain routing rule requires a domain.')
                    ->atPath('domain')
                    ->addViolation();
            }

            return;
        }

        if ($this->isProjectType()) {
            $this->validateArguments($context);

            if ($this->project === null) {
                $context->buildViolation('A project routing rule requires a project.')
                    ->atPath('project')
                    ->addViolation();
            }

            return;
        }

        if ($this->isApplicationType()) {
            if ($this->application === null) {
                $context->buildViolation('An application routing rule requires an application.')
                    ->atPath('application')
                    ->addViolation();
            }

            if (!in_array($this->getApplicationType(), array_values(self::getApplicationTypeChoices()), true)) {
                $context->buildViolation('An application routing rule requires an application type.')
                    ->atPath('applicationType')
                    ->addViolation();

                return;
            }

            if ($this->isApplicationArgumentsType()) {
                $this->validateArguments($context);
            }

            if ($this->isApplicationRouteType() && trim((string) $this->route) === '') {
                $context->buildViolation('A route-based application rule requires a route.')
                    ->atPath('route')
                    ->addViolation();
            }

            return;
        }

        if ($this->isRedirectRule()) {
            $this->validateRedirectRule($context);

            return;
        }

        if ($this->isRouteRule()) {
            $this->validateRouteRule($context);
        }
    }

    public function validateArguments(ExecutionContextInterface $context): void
    {
        if ($this->arguments === []) {
            $context->buildViolation('A domain, project, or application routing rule requires at least one argument.')
                ->atPath('arguments')
                ->addViolation();
        }

        foreach ($this->arguments as $index => $argument) {
            if ($this->isReservedArgument($argument)) {
                $context->buildViolation(sprintf('The routing rule argument "%s" is reserved.', $argument))
                    ->atPath(sprintf('arguments[%d]', $index))
                    ->addViolation();
            }

            if (!$this->isValidArgumentRegex($argument)) {
                $context->buildViolation(sprintf('The routing rule argument "%s" is not a valid regular expression segment.', $argument))
                    ->atPath(sprintf('arguments[%d]', $index))
                    ->addViolation();
            }
        }
    }

    private function isReservedArgument(string $argument): bool
    {
        return in_array(strtolower(trim($argument)), self::RESERVED_ARGUMENTS, true);
    }

    private function isValidArgumentRegex(string $argument): bool
    {
        if ($argument === '*') {
            return true;
        }

        return @preg_match('~^(?:'.str_replace('~', '\\~', $argument).')$~u', '') !== false;
    }

    private function validateRedirectRule(ExecutionContextInterface $context): void
    {
        if (!in_array($this->redirectType, array_values(self::getRedirectTypeChoices()), true)) {
            $context->buildViolation('A redirect rule requires a redirect type.')
                ->atPath('redirectType')
                ->addViolation();
        }

        if ($this->isDomainRedirectType() && $this->domain === null) {
            $context->buildViolation('A domain redirect rule requires a domain.')
                ->atPath('domain')
                ->addViolation();
        }

        if ($this->isProjectRedirectType() && $this->project === null) {
            $context->buildViolation('A project redirect rule requires a project.')
                ->atPath('project')
                ->addViolation();
        }

        if (!in_array($this->redirectTarget, array_values(self::getRedirectTargetChoices()), true)) {
            $context->buildViolation('A redirect rule requires a redirect target.')
                ->atPath('redirectTarget')
                ->addViolation();
        }

        if ($this->isDomainRedirectTarget() && $this->redirectDomain === null) {
            $context->buildViolation('A domain redirect target requires a redirect domain.')
                ->atPath('redirectDomain')
                ->addViolation();
        }

        if ($this->isProjectRedirectTarget() && $this->redirectProject === null) {
            $context->buildViolation('A project redirect target requires a redirect project.')
                ->atPath('redirectProject')
                ->addViolation();
        }

        if ($this->isDomainAndProjectRedirectTarget() && $this->redirectDomain === null) {
            $context->buildViolation('A domain and project redirect target requires a redirect domain.')
                ->atPath('redirectDomain')
                ->addViolation();
        }

        if ($this->isDomainAndProjectRedirectTarget() && $this->redirectProject === null) {
            $context->buildViolation('A domain and project redirect target requires a redirect project.')
                ->atPath('redirectProject')
                ->addViolation();
        }
    }

    private function validateRouteRule(ExecutionContextInterface $context): void
    {
        if (!in_array($this->routeType, array_values(self::getRouteTypeChoices()), true)) {
            $context->buildViolation('A route rule requires a route type.')
                ->atPath('routeType')
                ->addViolation();
        }

        if ($this->isDomainRouteType() && $this->domain === null) {
            $context->buildViolation('A domain route rule requires a domain.')
                ->atPath('domain')
                ->addViolation();
        }

        if ($this->isProjectRouteType() && $this->project === null) {
            $context->buildViolation('A project route rule requires a project.')
                ->atPath('project')
                ->addViolation();
        }

        if (trim((string) $this->route) === '') {
            $context->buildViolation('A route rule requires a route.')
                ->atPath('route')
                ->addViolation();
        }
    }
}
