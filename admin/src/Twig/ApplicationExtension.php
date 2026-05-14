<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Application;
use Symfonicat\Service\ApplicationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('application_helper', $this->applicationHelper(...), ['is_safe' => ['html']]),
            new TwigFunction('path_application', $this->pathApplication(...)),
        ];
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return [
                'application' => null,
            ];
        }

        $application = $this->applicationService->load();

        return [
            'application' => $application,
        ];
    }

    public function pathApplication(Application|string $application, string|array|object|null $pathOrParameters = null, string|array|object|null $pathOrParameters2 = null): string
    {
        [$path, $arguments] = $this->normalizePathApplicationInputs($pathOrParameters, $pathOrParameters2);

        return $this->applicationService->path($application, $path, $arguments);
    }

    /**
     * @param string|array<int|string, mixed>|object|null $pathOrParameters
     * @param string|array<int|string, mixed>|object|null $pathOrParameters2
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function normalizePathApplicationInputs(string|array|object|null $pathOrParameters, string|array|object|null $pathOrParameters2): array
    {
        $path = '';
        $parameters = null;

        foreach ([$pathOrParameters, $pathOrParameters2] as $argument) {
            if ($argument === null) {
                continue;
            }

            if (is_string($argument)) {
                if ($path === '') {
                    $path = $argument;

                    continue;
                }

                throw new \InvalidArgumentException('path_application() accepts at most one string path argument.');
            }

            $normalized = array_values($this->normalizePathParameters($argument));
            $parameters = $parameters === null ? $normalized : array_values(array_merge($parameters, $normalized));
        }

        return [$path, $parameters ?? []];
    }

    /**
     * @param array<int|string, mixed>|object $parameters
     *
     * @return array<int|string, mixed>
     */
    private function normalizePathParameters(array|object $parameters): array
    {
        if (is_array($parameters)) {
            return $parameters;
        }

        if ($parameters instanceof \Traversable) {
            return iterator_to_array($parameters);
        }

        return get_object_vars($parameters);
    }

    private function applicationHelper(): string
    {
        $application = $this->applicationService->load();
        $request = $this->requestStack->getCurrentRequest();
        $data = null;

        if ($application instanceof Application && $request !== null) {
            $id = (string) $application->getId();
            $data = [
                'id' => $id,
                'csrfToken' => $this->applicationService->moduleRequestToken($id),
                'requestHeader' => 'X-Symfonicat-Application-Request',
                'tokenHeader' => 'X-Symfonicat-Application-Token',
            ];

            $redirectTo = $request->attributes->get('symfonicat_application_redirect_target');
            if (is_string($redirectTo) && $redirectTo !== '') {
                $data['redirectTo'] = $redirectTo;
            }
        }

        return sprintf(
            "window.application = %s\nconsole.log('[window.application]', window.application)\nconsole.log('----------------------')",
            json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        );
    }
}
