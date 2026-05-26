<?php

namespace Symfonicat\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfonicat\Entity\Middleware;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RuntimeMiddlewareRunner
{
    /**
     * @param iterable<MiddlewareInterface> $availableMiddlewares
     */
    public function __construct(
        #[AutowireIterator('symfonicat.middleware')]
        private readonly iterable $availableMiddlewares,
        private readonly HttpMessageFactoryInterface $httpMessageFactory,
        private readonly HttpFoundationFactoryInterface $httpFoundationFactory,
    ) {
    }

    /**
     * @param iterable<Middleware> $middlewareRows
     */
    public function run(Request $request, Response $response, iterable $middlewareRows): Response
    {
        $middlewares = $this->resolveMiddlewares($middlewareRows);
        if ($middlewares === []) {
            return $response;
        }

        $handler = new class($response, $this->httpMessageFactory) implements RequestHandlerInterface {
            public function __construct(
                private readonly Response $response,
                private readonly HttpMessageFactoryInterface $httpMessageFactory,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return $this->httpMessageFactory->createResponse($this->response);
            }
        };

        $processor = array_reduce(
            array_reverse($middlewares),
            static fn (\Closure $stack, MiddlewareInterface $middleware): \Closure => static fn (ServerRequestInterface $request): ResponseInterface => $middleware->process($request, new class($stack) implements RequestHandlerInterface {
                public function __construct(private readonly \Closure $stack)
                {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return ($this->stack)($request);
                }
            }),
            static fn (ServerRequestInterface $request): ResponseInterface => $handler->handle($request),
        );

        return $this->httpFoundationFactory->createResponse($processor($this->httpMessageFactory->createRequest($request)));
    }

    /**
     * @param iterable<Middleware> $middlewareRows
     *
     * @return list<\Psr\Http\Server\MiddlewareInterface>
     */
    private function resolveMiddlewares(iterable $middlewareRows): array
    {
        $available = $this->availableMiddlewaresByClass();
        $resolved = [];
        $seen = [];

        foreach ($middlewareRows as $middlewareRow) {
            if (!$middlewareRow instanceof Middleware) {
                continue;
            }

            $class = trim($middlewareRow->getClass());
            if ($class === '' || isset($seen[$class])) {
                continue;
            }

            if (isset($available[$class])) {
                $resolved[] = $available[$class];
            }

            $seen[$class] = true;
        }

        return $resolved;
    }

    /**
     * @return array<class-string, MiddlewareInterface>
     */
    private function availableMiddlewaresByClass(): array
    {
        $middlewares = [];

        foreach ($this->availableMiddlewares as $middleware) {
            if ($middleware instanceof MiddlewareInterface) {
                $middlewares[$middleware::class] = $middleware;
            }
        }

        return $middlewares;
    }
}
