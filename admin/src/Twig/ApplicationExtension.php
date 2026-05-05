<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Application;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
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

        $application = $this->requestStack->getCurrentRequest()?->attributes->get('application');

        return [
            'application' => $application instanceof Application ? $application : null,
        ];
    }

    public function pathApplication(Application|string $application, string|array|null $path = null, array $arguments = []): string
    {
        $id = $application instanceof Application ? $application->getId(true) : $application;
        $segments = ['/application', trim((string) $id, '/')];

        if (is_string($path) && $path !== '') {
            $segments[] = trim($path, '/');
        } elseif (is_array($path)) {
            $arguments = $path + $arguments;
        }

        $url = implode('/', array_filter($segments, static fn (string $segment): bool => $segment !== ''));
        if ($arguments !== []) {
            $url .= '?'.http_build_query($arguments);
        }

        return $url;
    }

    private function applicationHelper(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $application = $request?->attributes->get('application');
        $data = null;

        if ($application instanceof Application && $request !== null) {
            $id = (string) $application->getId();
            $data = [
                'id' => $id,
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
