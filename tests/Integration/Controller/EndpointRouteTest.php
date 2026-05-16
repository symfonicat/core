<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Support\SymfonicatKernelTestCase;

final class EndpointRouteTest extends SymfonicatKernelTestCase
{
    public function testDeleteRouteDoesNotMatchTheEditRoute(): void
    {
        $router = self::getContainer()->get('router');
        $router->getContext()->setMethod('POST');

        $matched = $router->match('/admin/e/test/delete');

        self::assertSame('symfonicat_endpoint_delete', $matched['_route']);
        self::assertSame('test', $matched['id']);
    }
}
