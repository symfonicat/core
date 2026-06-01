<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Service\PathService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PathServiceTest extends TestCase
{
    public function testEmptyArgumentsMatchRootAndTrailingPathsWhenCatchIsEnabled(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/symfonicat/test/docs'));

        $service = new PathService($requestStack);

        self::assertTrue($service->matchesArguments([], null, true));
        self::assertFalse($service->matchesArguments([], null, false));
    }

    public function testExplicitArgumentsStillMatchTrailingPathsWhenCatchIsEnabled(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/symfonicat/test/docs'));

        $service = new PathService($requestStack);

        self::assertTrue($service->matchesArguments(['symfonicat', 'test'], null, true));
        self::assertFalse($service->matchesArguments(['symfonicat', 'test'], null, false));
    }
}
