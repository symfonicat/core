<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Repository\ApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApplicationService
{
    private const MODULE_REQUEST_TOKEN_TTL = 3600;

    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PathService $pathService,
        private readonly RequestStack $requestStack,
        private readonly RoutingRuleService $routingRuleService,
        private readonly string $appSecret,
        private readonly string $projectDir,
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

    /**
     * @param (callable(list<string>): bool)|null $confirmApplicationCreation
     *
     * @return array{created: list<array{id: string}>}
     */
    public function sync(?callable $confirmApplicationCreation = null): array
    {
        $filesystemApplications = $this->discoverFilesystemApplications();
        $databaseApplications = $this->indexDatabaseApplications();

        $missingApplicationIds = array_values(array_diff($filesystemApplications, array_keys($databaseApplications)));
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
    private function discoverFilesystemApplications(): array
    {
        $applicationDirectories = glob($this->projectDir.'/assets/application/*', GLOB_ONLYDIR) ?: [];
        sort($applicationDirectories, SORT_STRING);

        return array_values(array_map('basename', $applicationDirectories));
    }

    /**
     * @return array<string, Application>
     */
    private function indexDatabaseApplications(): array
    {
        $applications = [];

        foreach ($this->applicationRepository->findAllOrderedById() as $application) {
            $applicationId = $application->getId();
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

        $application = $this->applicationRepository->find($applicationId);

        if ($application instanceof Application) {
            $request->attributes->set('application', $application);
        }

        return $application instanceof Application ? $application : null;
    }

    private function isApplicationModuleRequestContext(Request $request): bool
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/m/')) {
            return false;
        }

        return trim((string) $request->headers->get('X-Symfonicat-Application-Request')) === '1';
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path;
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
