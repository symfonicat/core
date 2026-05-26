<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Middleware;
use Symfonicat\Entity\Subdomain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Error\LoaderError;

final class RuntimeRenderer
{
    public const TARGET_DOMAIN = 'domain';
    public const TARGET_SUBDOMAIN = 'subdomain';
    public const TARGET_ENDPOINT = 'endpoint';

    public function __construct(
        private readonly Environment $twig,
        private readonly RuntimeMiddlewareRunner $middlewareRunner,
        private readonly ModuleRequestContextStore $moduleRequestContextStore,
    ) {
    }

    public function render(Request $request, ?string $target = null): Response
    {
        $target ??= trim((string) $request->attributes->get('symfonicat_runtime_target'));
        if (!in_array($target, [self::TARGET_DOMAIN, self::TARGET_SUBDOMAIN, self::TARGET_ENDPOINT], true)) {
            throw new NotFoundHttpException();
        }

        $entity = $this->entityForTarget($request, $target);
        $template = $this->resolveTemplate($target, $this->templateId($entity));
        $moduleRequest = $this->moduleRequestContextStore->issue($request, $entity);
        $request->attributes->set('request', $moduleRequest);
        $response = new Response($this->twig->render($template, [
            'domain' => $request->attributes->get('domain'),
            'subdomain' => $request->attributes->get('subdomain'),
            'endpoint' => $request->attributes->get('endpoint'),
            'application' => $request->attributes->get('application'),
        ]));

        return $this->middlewareRunner->run($request, $response, $this->middlewaresForTarget($request, $target));
    }

    private function entityForTarget(Request $request, string $target): Domain|Subdomain|Endpoint
    {
        $entity = match ($target) {
            self::TARGET_DOMAIN => $request->attributes->get('domain'),
            self::TARGET_SUBDOMAIN => $request->attributes->get('subdomain'),
            self::TARGET_ENDPOINT => $request->attributes->get('endpoint'),
            default => null,
        };

        if (!$entity instanceof Domain && !$entity instanceof Subdomain && !$entity instanceof Endpoint) {
            throw new NotFoundHttpException();
        }

        return $entity;
    }

    /**
     * @return list<Middleware>
     */
    private function middlewaresForTarget(Request $request, string $target): array
    {
        $rows = [];

        $domain = $request->attributes->get('domain');
        if ($domain instanceof Domain) {
            $rows = array_merge($rows, $domain->getMiddlewares()->toArray());
        }

        $subdomain = $request->attributes->get('subdomain');
        if (($target === self::TARGET_SUBDOMAIN || $target === self::TARGET_ENDPOINT) && $subdomain instanceof Subdomain) {
            $rows = array_merge($rows, $subdomain->getMiddlewares()->toArray());
        }

        $endpoint = $request->attributes->get('endpoint');
        if ($target === self::TARGET_ENDPOINT && $endpoint instanceof Endpoint) {
            $rows = array_merge($rows, $endpoint->getMiddlewares()->toArray());
        }

        $seen = [];

        return array_values(array_filter($rows, static function (mixed $row) use (&$seen): bool {
            if (!$row instanceof Middleware) {
                return false;
            }

            $key = $row->getId() ?? $row->getClass();
            if (isset($seen[$key])) {
                return false;
            }

            $seen[$key] = true;

            return true;
        }));
    }

    private function resolveTemplate(string $target, string $id): string
    {
        $override = sprintf('%s/overrides/%s.html.twig', $target, $id);

        try {
            $this->twig->load($override);

            return $override;
        } catch (LoaderError) {
            return sprintf('%s/main.html.twig', $target);
        }
    }

    private function templateId(Domain|Subdomain|Endpoint $entity): string
    {
        $id = trim((string) $entity->getId(false), " \t\n\r\0\x0B/");
        if ($id === '') {
            throw new NotFoundHttpException();
        }

        return basename(str_replace('\\', '/', $id));
    }
}
