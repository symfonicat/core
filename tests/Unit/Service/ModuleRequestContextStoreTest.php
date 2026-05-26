<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Service\ModuleRequestContextStore;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class ModuleRequestContextStoreTest extends TestCase
{
    public function testIssueAndResolveEndpointContext(): void
    {
        $store = new ModuleRequestContextStore();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $endpoint = (new Endpoint())->setId('core/test');
        $context = $store->issue($request, $endpoint);

        $moduleRequest = Request::create('/m/symfonicat/analytics/main', 'POST', [], [], [], [
            'HTTP_X_SYMFONICAT_MODULE_CONTEXT' => $context['context_id'],
            'HTTP_X_CSRF_TOKEN' => $context['token'],
        ]);
        $moduleRequest->setSession($session);

        self::assertSame('core/test', $store->resolve($moduleRequest)['endpoint_id']);
    }

    public function testResolveRejectsInvalidToken(): void
    {
        $store = new ModuleRequestContextStore();
        $session = new Session(new MockArraySessionStorage());
        $request = Request::create('/');
        $request->setSession($session);

        $endpoint = (new Endpoint())->setId('core/test');
        $context = $store->issue($request, $endpoint);

        $moduleRequest = Request::create('/m/symfonicat/analytics/main', 'POST', [], [], [], [
            'HTTP_X_SYMFONICAT_MODULE_CONTEXT' => $context['context_id'],
            'HTTP_X_CSRF_TOKEN' => 'invalid',
        ]);
        $moduleRequest->setSession($session);

        self::assertNull($store->resolve($moduleRequest));
    }
}
