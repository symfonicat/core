<?php

namespace App\Tests\Unit\Service;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfonicat\Entity\Middleware;
use Symfonicat\Service\RuntimeMiddlewareRunner;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RuntimeMiddlewareRunnerTest extends TestCase
{
    public function testPassesModuleJsonToPsrMiddleware(): void
    {
        $seen = null;
        $middleware = new class($seen) implements MiddlewareInterface {
            public function __construct(private mixed &$seen)
            {
            }

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                $this->seen = $request->getAttribute('module_json');

                return $handler->handle($request);
            }
        };

        $messageFactory = $this->createStub(HttpMessageFactoryInterface::class);
        $messageFactory->method('createRequest')->willReturnCallback(static function (Request $request): ServerRequestInterface {
            $factory = new Psr17Factory();
            $psrRequest = $factory->createServerRequest($request->getMethod(), $request->getUri());

            return $psrRequest->withHeader('Content-Type', $request->headers->get('Content-Type', ''));
        });
        $messageFactory->method('createResponse')->willReturnCallback(static fn (Response $response): ResponseInterface => (new Psr17Factory())->createResponse($response->getStatusCode())->withBody((new Psr17Factory())->createStream($response->getContent() ?? '')));

        $foundationFactory = $this->createStub(HttpFoundationFactoryInterface::class);
        $foundationFactory->method('createResponse')->willReturnCallback(static fn (ResponseInterface $response): Response => new Response((string) $response->getBody(), $response->getStatusCode()));

        $runner = new RuntimeMiddlewareRunner([$middleware], $messageFactory, $foundationFactory);

        $request = Request::create('/m/symfonicat/analytics/main', 'POST');
        $request->attributes->set('module_json', ['test' => true]);

        $middlewareRow = (new Middleware())->setClass($middleware::class);
        $response = $runner->run($request, new Response('ok'), [$middlewareRow]);

        self::assertSame(['test' => true], $seen);
        self::assertSame('ok', $response->getContent());
    }
}
