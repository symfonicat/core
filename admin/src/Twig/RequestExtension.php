<?php

namespace Symfonicat\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class RequestExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('request_helper', $this->renderHelper(...), ['is_safe' => ['html']]),
        ];
    }

    public function renderHelper(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $requestContext = $request?->attributes->get('request');
        $requestContext = is_array($requestContext) ? [
            'contextId' => $requestContext['context_id'] ?? $requestContext['contextId'] ?? null,
            'token' => $requestContext['token'] ?? null,
        ] : null;

        return json_encode(
            $requestContext,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }
}
