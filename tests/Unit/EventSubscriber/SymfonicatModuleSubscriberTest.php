<?php

namespace App\Tests\Unit\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfonicat\EventSubscriber\SymfonicatModuleSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class SymfonicatModuleSubscriberTest extends TestCase
{
    public function testDecodesBrotliEncodedModuleRequestsOnModulePath(): void
    {
        $event = $this->requestEvent('/m/symfonicat/analytics/main', [
            'HTTP_CONTENT_ENCODING' => 'br',
        ], '{"hello":"world"}');

        (new SymfonicatModuleSubscriber())->onKernelRequest($event);

        self::assertSame([
            'hello' => 'world',
        ], $event->getRequest()->attributes->get('module_json'));
    }

    public function testIgnoresNonModulePaths(): void
    {
        $event = $this->requestEvent('/docs', [
            'HTTP_CONTENT_ENCODING' => 'br',
        ], '{"hello":"world"}');

        (new SymfonicatModuleSubscriber())->onKernelRequest($event);

        self::assertFalse($event->getRequest()->attributes->has('module_json'));
    }

    public function testDecodesPlainModuleRequests(): void
    {
        $event = $this->requestEvent('/m/symfonicat/analytics/main', [], '{"hello":"world"}');

        (new SymfonicatModuleSubscriber())->onKernelRequest($event);

        self::assertSame([
            'hello' => 'world',
        ], $event->getRequest()->attributes->get('module_json'));
    }

    public function testInvalidModuleJsonThrows(): void
    {
        $event = $this->requestEvent('/m/symfonicat/analytics/main', [
            'HTTP_CONTENT_ENCODING' => 'br',
        ], "\x00\x01\x02not-json");

        $this->expectException(\JsonException::class);
        (new SymfonicatModuleSubscriber())->onKernelRequest($event);
    }

    /**
     * @param array<string, string> $server
     */
    private function requestEvent(string $path, array $server, string $content): RequestEvent
    {
        $request = Request::create($path, 'POST', [], [], [], $server, $content);

        return new RequestEvent(
            new class implements HttpKernelInterface {
                public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
                {
                    return new Response();
                }
            },
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
