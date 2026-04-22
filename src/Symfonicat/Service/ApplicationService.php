<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Repository\ApplicationRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ApplicationService
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PathService $pathService,
        private readonly RequestStack $requestStack,
        private readonly RoutingRuleService $routingRuleService,
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

    private function loadFromModuleRequestContext(Request $request): ?Application
    {
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/m/')) {
            return null;
        }

        $applicationId = trim((string) $request->headers->get('X-Symfonicat-Application'));
        $applicationPath = trim((string) $request->headers->get('X-Symfonicat-Application-Path'));

        if ($applicationId === '' || $applicationPath === '') {
            return null;
        }

        $application = $this->loadFromPath($applicationPath);
        if (!$application instanceof Application || $application->getId() !== $applicationId) {
            return null;
        }

        $request->attributes->set('application', $application);

        return $application;
    }

    private function normalizePath(string $path): string
    {
        $path = parse_url($path, PHP_URL_PATH) ?: $path;
        $path = trim($path, '/');

        return $path === '' ? '/' : '/'.$path;
    }
}
