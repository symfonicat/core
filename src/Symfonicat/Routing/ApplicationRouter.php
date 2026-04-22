<?php

namespace Symfonicat\Routing;

use Symfonicat\Service\ApplicationUrlService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\CacheWarmer\WarmableInterface;
use Symfony\Component\Routing\Exception\InvalidParameterException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Matcher\RequestMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

final class ApplicationRouter implements RouterInterface, RequestMatcherInterface, WarmableInterface
{
    public function __construct(
        private readonly RouterInterface $inner,
        private readonly ApplicationUrlService $applicationUrlService,
    ) {
    }

    /**
     * @param array<string, mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        if ($name !== 'symfonicat_application') {
            return $this->inner->generate($name, $parameters, $referenceType);
        }

        $path = $this->applicationUrlService->pathFromRouteParameters($parameters);
        $parameters = $this->removeApplicationParameters($parameters);
        $fragment = (string) ($parameters['_fragment'] ?? '');
        unset($parameters['_fragment']);

        if ($parameters !== []) {
            $path .= '?'.http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        }

        if ($fragment !== '') {
            $path .= '#'.rawurlencode($fragment);
        }

        return $this->applyReferenceType($path, $referenceType);
    }

    public function match(string $pathinfo): array
    {
        return $this->inner->match($pathinfo);
    }

    public function matchRequest(Request $request): array
    {
        if ($this->inner instanceof RequestMatcherInterface) {
            return $this->inner->matchRequest($request);
        }

        return $this->inner->match($request->getPathInfo());
    }

    public function getRouteCollection(): RouteCollection
    {
        return $this->inner->getRouteCollection();
    }

    public function setContext(RequestContext $context): void
    {
        $this->inner->setContext($context);
    }

    public function getContext(): RequestContext
    {
        return $this->inner->getContext();
    }

    /**
     * @return list<class-string|string>
     */
    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        if (!$this->inner instanceof WarmableInterface) {
            return [];
        }

        return $this->inner->warmUp($cacheDir, $buildDir);
    }

    /**
     * @param array<string, mixed> $parameters
     *
     * @return array<string, mixed>
     */
    private function removeApplicationParameters(array $parameters): array
    {
        unset($parameters['id'], $parameters['path'], $parameters['arguments']);

        return $parameters;
    }

    private function applyReferenceType(string $path, int $referenceType): string
    {
        $context = $this->inner->getContext();
        $basePath = $context->getBaseUrl().$path;

        if ($referenceType === UrlGeneratorInterface::ABSOLUTE_PATH) {
            return $basePath;
        }

        if ($referenceType === UrlGeneratorInterface::RELATIVE_PATH) {
            return UrlGenerator::getRelativePath($context->getPathInfo(), $basePath);
        }

        $host = $context->getHost();
        $scheme = $context->getScheme();
        $port = '';
        $httpPort = $context->getHttpPort();
        $httpsPort = $context->getHttpsPort();

        if ($scheme === 'http' && $httpPort !== 80) {
            $port = ':'.$httpPort;
        } elseif ($scheme === 'https' && $httpsPort !== 443) {
            $port = ':'.$httpsPort;
        }

        if ($referenceType === UrlGeneratorInterface::NETWORK_PATH) {
            return sprintf('//%s%s%s', $host, $port, $basePath);
        }

        if ($referenceType === UrlGeneratorInterface::ABSOLUTE_URL) {
            return sprintf('%s://%s%s%s', $scheme, $host, $port, $basePath);
        }

        throw new InvalidParameterException(sprintf('Unsupported URL reference type "%d".', $referenceType));
    }
}
