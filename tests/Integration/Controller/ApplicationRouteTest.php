<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Support\SymfonicatKernelTestCase;

final class ApplicationRouteTest extends SymfonicatKernelTestCase
{
    public function testDeleteRouteDoesNotMatchTheEditRoute(): void
    {
        $router = self::getContainer()->get('router');
        $router->getContext()->setMethod('POST');

        $matched = $router->match('/core/a/example-test/delete');

        self::assertSame('symfonicat_application_delete', $matched['_route']);
        self::assertSame('example-test', $matched['id']);
    }
}
