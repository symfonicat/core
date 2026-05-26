<?php

namespace App\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Service\ApplicationService;
use Symfonicat\Twig\ApplicationExtension;
use Symfonicat\Twig\EndpointExtension;
use Symfonicat\Twig\RequestExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RuntimeContextExtensionTest extends TestCase
{
    public function testApplicationHelperReturnsNullWhenNoApplicationIsLoaded(): void
    {
        $extension = new ApplicationExtension($this->createStub(ApplicationService::class));

        self::assertSame("null", $extension->renderHelper());
    }

    public function testApplicationHelperReturnsLoadedApplicationShape(): void
    {
        $application = (new Application())
            ->setId('core/example')
            ->setName('Example App')
            ->setType(Application::TYPE_DOMAIN);

        $service = $this->createStub(ApplicationService::class);
        $service->method('load')->willReturn($application);

        $extension = new ApplicationExtension($service);

        self::assertJsonStringEqualsJsonString(
            '{"id":"core/example","name":"Example App","type":"domain"}',
            $extension->renderHelper(),
        );
    }

    public function testEndpointHelperReturnsLoadedEndpointId(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('endpoint', (new Endpoint())->setId('core/test'));
        $requestStack->push($request);

        $extension = new EndpointExtension($requestStack);

        self::assertJsonStringEqualsJsonString('{"id":"core/test"}', $extension->renderHelper());
    }

    public function testRequestHelperReturnsStoredRequestContextOrNull(): void
    {
        $requestStack = new RequestStack();
        $request = Request::create('/');
        $request->attributes->set('request', [
            'contextId' => 'abc',
            'token' => '123',
        ]);
        $requestStack->push($request);

        $extension = new RequestExtension($requestStack);

        self::assertJsonStringEqualsJsonString(
            '{"contextId":"abc","token":"123"}',
            $extension->renderHelper(),
        );

        $requestStack->pop();
        self::assertSame("null", $extension->renderHelper());
    }
}
