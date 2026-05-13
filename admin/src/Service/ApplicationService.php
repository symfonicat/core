<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\RoutingRuleRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException;
use Symfony\Component\Routing\RouterInterface;

class ApplicationService
{
    private const MODULE_REQUEST_TOKEN_TTL = 3600;

    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly RoutingRuleRepository $routingRuleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PathService $pathService,
        private readonly RequestStack $requestStack,
        private readonly RouterInterface $frameworkRouter,
        private readonly RoutingRuleService $routingRuleService,
        private readonly PackageDiscoveryService $packageDiscoveryService,
        private readonly string $appSecret,
    ) {
    }

    public function load(): ?Application
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || str_starts_with($request->getPathInfo(), '/admin')) {
            return null;
        }

        $application = $request->attributes->get('application');

        if ($application instanceof Application) {
            return $application;
        }

        $application = $this->loadFromRoute((string) $request->attributes->get('_route', ''));
        if ($application instanceof Application) {
            return $application;
        }

        if ($request->attributes->getBoolean('symfonicat_routing_rule_active')) {
            return null;
        }

        $application = $this->loadFromPath($this->pathService->path());
        if ($application instanceof Application) {
            $request->attributes->set('application', $application);

            return $application;
        }

        return $this->loadFromModuleRequestContext($request);
    }

    public function loadFromPath(string $path): ?Application
    {
        $path = $this->normalizePath($path);

        if (str_starts_with($path, '/admin')) {
            return null;
        }

        $rule = $this->routingRuleService->getApplicationRuleForPath($path);

        return $rule?->getApplication();
    }

    public function loadFromRoute(?string $route = null): ?Application
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request || str_starts_with($request->getPathInfo(), '/admin')) {
            return null;
        }

        $route ??= (string) $request->attributes->get('_route', '');
        $route = trim($route);
        if ($route === '') {
            return null;
        }

        $rule = $this->routingRuleRepository->findOneTypeApplicationByRoute($route);
        $application = $rule?->getApplication();

        if ($application instanceof Application) {
            $request->attributes->set('application', $application);
            $request->attributes->set('symfonicat_application_rule', $rule);
        }

        return $application instanceof Application ? $application : null;
    }

    /**
     * @param string|array<int, mixed>|null $path
     * @param array<int, mixed> $arguments
     */
    public function path(Application|string $application, string|array|null $path = null, array $arguments = []): string
    {
        if (is_array($path)) {
            $arguments = $path;
            $path = null;
        }

        $applicationId = $application instanceof Application ? (string) $application->getId(true) : trim((string) $application);
        $applicationId = trim($applicationId);
        if ($applicationId === '') {
            throw new MissingMandatoryParametersException('The "id" parameter is required for the "symfonicat_application" route.');
        }

        $rule = $this->getRuleForApplication($applicationId);
        if (!$rule instanceof RoutingRule) {
            throw new InvalidParameterException(sprintf('Application "%s" does not have an application routing rule.', $applicationId));
        }

        return $this->pathFromRule($rule, (string) $path, $arguments);
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function pathFromRouteParameters(array $parameters): string
    {
        $id = (string) ($parameters['id'] ?? '');
        $vendor = trim((string) ($parameters['vendor'] ?? ''));
        if ($vendor !== '' && $id !== '' && !str_contains($id, '/')) {
            $id = $vendor.'/'.$id;
        }

        $path = $parameters['path'] ?? null;
        $arguments = $parameters['arguments'] ?? [];

        if (is_array($path) && $arguments === []) {
            $arguments = $path;
            $path = null;
        }

        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        return $this->path($id, is_array($path) ? null : (string) ($path ?? ''), array_values($arguments));
    }

    public function getRuleForApplication(Application|string $application, ?string $applicationType = null): ?RoutingRule
    {
        $applicationId = $application instanceof Application ? (string) $application->getId(true) : trim((string) $application);
        if ($applicationId === '') {
            return null;
        }

        return match ($applicationType) {
            RoutingRule::APPLICATION_TYPE_ARGUMENTS => $this->routingRuleRepository->findOneTypeApplicationArgumentsByApplicationId($applicationId),
            RoutingRule::APPLICATION_TYPE_ROUTE => $this->routingRuleRepository->findOneTypeApplicationRouteByApplicationId($applicationId),
            default => $this->routingRuleRepository->findOneTypeApplicationArgumentsByApplicationId($applicationId)
                ?? $this->routingRuleRepository->findOneTypeApplicationRouteByApplicationId($applicationId)
                ?? $this->routingRuleRepository->findOneTypeApplicationByApplicationId($applicationId),
        };
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function pathFromRule(RoutingRule $rule, string $path = '', array $arguments = []): string
    {
        if ($rule->isApplicationRouteType()) {
            return $this->pathFromRouteRule($rule, $path, $arguments);
        }

        if ($rule->isApplicationDomainType() || $rule->isApplicationProjectType() || $rule->isApplicationDomainProjectType()) {
            if ($arguments !== []) {
                throw new InvalidParameterException(sprintf(
                    'Application "%s" domain and project rules do not support wildcard path arguments.',
                    (string) $rule->getApplication()?->getId(),
                ));
            }

            return $this->segmentsToPath($this->pathSegments($path));
        }

        $replacementArguments = array_values(array_map(
            static fn (mixed $argument): string => trim((string) $argument, " \t\n\r\0\x0B/"),
            $arguments,
        ));

        $segments = [];

        foreach ($rule->getArguments() as $argument) {
            $argument = trim($argument, " \t\n\r\0\x0B/");
            if ($argument === '') {
                continue;
            }

            if ($argument === '*') {
                $replacement = array_shift($replacementArguments);
                $segments[] = $replacement === null || $replacement === '' ? '*' : $replacement;

                continue;
            }

            $argument = rtrim($argument, '*');
            if ($argument !== '') {
                $segments[] = $argument;
            }
        }

        foreach ($this->pathSegments($path) as $pathSegment) {
            $segments[] = $pathSegment;
        }

        return $this->segmentsToPath($segments);
    }

    /**
     * @param (callable(list<string>): bool)|null $confirmApplicationCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmApplicationCreation = null): array
    {
        $this->assertNoDuplicateApplications();

        $packageApplications = $this->discoverPackageApplications();
        $databaseApplications = $this->indexDatabaseApplications();

        $missingApplicationIds = array_values(array_diff($packageApplications, array_keys($databaseApplications)));
        sort($missingApplicationIds, SORT_STRING);

        if ($missingApplicationIds === []) {
            return ['created' => []];
        }

        if ($confirmApplicationCreation !== null && !(bool) $confirmApplicationCreation($missingApplicationIds)) {
            throw new \RuntimeException('Aborted creating missing application rows.');
        }

        $created = [];

        foreach ($missingApplicationIds as $applicationId) {
            $application = (new Application())->setId($applicationId);

            $this->entityManager->persist($application);
            $created[] = ['id' => $applicationId];
        }

        $this->entityManager->flush();

        return ['created' => $created];
    }

    /**
     * @return list<string>
     */
    private function discoverPackageApplications(): array
    {
        return array_keys($this->packageDiscoveryService->discoverEntryDirectories('applications'));
    }

    /**
     * @return array<string, Application>
     */
    private function indexDatabaseApplications(): array
    {
        $applications = [];

        foreach ($this->applicationRepository->findAllOrderedById() as $application) {
            $applicationId = $application->getId(true);
            if ($applicationId === null || $applicationId === '') {
                continue;
            }

            $applications[$applicationId] = $application;
        }

        return $applications;
    }

    public function isApplicationModuleRequest(): bool
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request instanceof Request && $this->isApplicationModuleRequestContext($request);
    }

    public function loadFromModuleRequest(): ?Application
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request instanceof Request) {
            return null;
        }

        return $this->loadFromModuleRequestContext($request);
    }

    public function csrfTokenId(string $applicationId): string
    {
        return 'symfonicat_application_module_'.$applicationId;
    }

    public function moduleRequestToken(string $applicationId): string
    {
        $payload = $this->base64UrlEncode(json_encode([
            'application' => $applicationId,
            'expires' => time() + self::MODULE_REQUEST_TOKEN_TTL,
            'nonce' => bin2hex(random_bytes(16)),
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signModuleRequestTokenPayload($payload);
    }

    private function loadFromModuleRequestContext(Request $request): ?Application
    {
        if (!$this->isApplicationModuleRequestContext($request)) {
            return null;
        }

        $applicationId = trim((string) $request->headers->get('X-Symfonicat-Application'));
        $token = trim((string) $request->headers->get('X-Symfonicat-Application-Token'));

        if ($applicationId === '' || $token === '') {
            return null;
        }

        if (!$this->isModuleRequestTokenValid($applicationId, $token)) {
            return null;
        }

        $application = $this->applicationRepository->findOneByFullOrCleanId($applicationId);

        if ($application instanceof Application) {
            $request->attributes->set('application', $application);
        }

        return $application instanceof Application ? $application : null;
    }

    private function assertNoDuplicateApplications(): void
    {
        $duplicates = $this->applicationRepository->findDuplicateCleanIdGroups();
        if ($duplicates === []) {
            return;
        }

        $details = array_map(
            static fn (array $group): string => sprintf('%s: %s', $group['cleanId'], implode(', ', $group['ids'])),
            $duplicates,
        );

        throw new \RuntimeException(sprintf(
            'Duplicate application ids detected: %s',
            implode('; ', $details),
        ));
    }

    private function isApplicationModuleRequestContext(Request $request): bool
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/m/')) {
            return false;
        }

        return trim((string) $request->headers->get('X-Symfonicat-Application-Request')) === '1';
    }

    /**
     * @param array<int, mixed> $arguments
     */
    private function pathFromRouteRule(RoutingRule $rule, string $path, array $arguments): string
    {
        if ($arguments !== []) {
            throw new InvalidParameterException(sprintf(
                'Application "%s" route rules do not support wildcard path arguments.',
                (string) $rule->getApplication()?->getId(),
            ));
        }

        $routeName = trim((string) $rule->getRoute());
        if ($routeName === '') {
            throw new InvalidParameterException(sprintf(
                'Application "%s" does not have a route name configured.',
                (string) $rule->getApplication()?->getId(),
            ));
        }

        $generatedPath = $this->frameworkRouter->generate($routeName);
        $suffixSegments = $this->pathSegments($path);

        if ($suffixSegments === []) {
            return $generatedPath;
        }

        return rtrim($generatedPath, '/').'/'.implode('/', array_map($this->encodeSegment(...), $suffixSegments));
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path;
    }

    /**
     * @return list<string>
     */
    private function pathSegments(string $path): array
    {
        $path = trim($path, " \t\n\r\0\x0B/");
        if ($path === '') {
            return [];
        }

        return array_values(array_filter(
            explode('/', $path),
            static fn (string $segment): bool => $segment !== '',
        ));
    }

    /**
     * @param list<string> $segments
     */
    private function segmentsToPath(array $segments): string
    {
        $segments = array_values(array_filter(
            array_map(
                static fn (string $segment): string => trim($segment, " \t\n\r\0\x0B/"),
                $segments,
            ),
            static fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return '/';
        }

        return '/'.implode('/', array_map($this->encodeSegment(...), $segments));
    }

    private function encodeSegment(string $segment): string
    {
        if ($segment === '*') {
            return '*';
        }

        return str_replace('%2A', '*', rawurlencode($segment));
    }

    private function isModuleRequestTokenValid(string $applicationId, string $token): bool
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return false;
        }

        [$payload, $signature] = $parts;
        if (!hash_equals($this->signModuleRequestTokenPayload($payload), $signature)) {
            return false;
        }

        $decoded = $this->base64UrlDecode($payload);
        if ($decoded === null) {
            return false;
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (!is_array($data)) {
            return false;
        }

        if (($data['application'] ?? null) !== $applicationId) {
            return false;
        }

        return is_int($data['expires'] ?? null) && $data['expires'] >= time();
    }

    private function signModuleRequestTokenPayload(string $payload): string
    {
        return $this->base64UrlEncode(hash_hmac('sha256', $payload, $this->appSecret, true));
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder !== 0) {
            $value .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
