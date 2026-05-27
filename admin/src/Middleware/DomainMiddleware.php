<?php

namespace Symfonicat\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

final class DomainMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->logger->info('DOMAIN MIDDLEWARE EXECUTING');

        // testing out /extensions/reverse Scriptling mod
        $this->logger->info('reversed: ' . scriptling_reverse('DOMAIN MIDDLEWARE EXECUTING'));

        // testing out /vendor/symfonicat/analytics/extensions/lowercase Scriptling Mod
        $this->logger->info('lowercase: ' . scriptling_analytics_lowercase('DOMAIN MIDDLEWARE EXECUTING'));

        return $handler->handle($request);
    }
}
