<?php

namespace Symfonicat\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mercure\HubRegistry;
use Symfony\Component\Mercure\Jwt\LcobucciFactory;
use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFunction;

final class MercureHelperExtension extends AbstractExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HubRegistry $hubRegistry,
        private readonly string $subscriberJwtKey = '',
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mercure_helper', $this->render(...), ['is_safe' => ['html']]),
        ];
    }

    public function render(): Markup
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return new Markup('', 'UTF-8');
        }

        $topic = $request->getUri();
        $hub = $this->hubRegistry->getHub();
        if ($this->subscriberJwtKey === '') {
            return new Markup('', 'UTF-8');
        }

        $factory = new LcobucciFactory($this->subscriberJwtKey);
        $token = $factory->create([$topic], []);
        $hubUrl = rtrim($hub->getPublicUrl(), '/');

        $payload = json_encode([
            'hubUrl' => $hubUrl,
            'topic' => $topic,
            'token' => $token,
        ], JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

        $html = sprintf(
            '<script id="mercure-config" type="application/json">%s</script>',
            $payload
        );

        return new Markup($html, 'UTF-8');
    }
}
