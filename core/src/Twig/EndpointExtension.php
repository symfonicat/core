<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\Endpoint;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class EndpointExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('endpoint_helper', $this->renderHelper(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderHelper(): string
    {
        $endpoint = $this->requestStack->getCurrentRequest()?->attributes->get('endpoint');

        return json_encode(
            $endpoint instanceof Endpoint ? [
                'id' => $endpoint->getId(),
            ] : null,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
