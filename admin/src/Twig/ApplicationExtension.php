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

    public function pathApplication(Application|string $application, string|array|null $path = null, array $arguments = []): string
    {
        return $this->applicationService->path($application, $path, $arguments);
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
