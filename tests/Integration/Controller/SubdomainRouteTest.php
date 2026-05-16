<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Support\SymfonicatKernelTestCase;

final class SubdomainRouteTest extends SymfonicatKernelTestCase
{
    public function testDeleteRouteDoesNotMatchTheEditRoute(): void
    {
        $router = self::getContainer()->get('router');
        $router->getContext()->setMethod('POST');

        $matched = $router->match('/admin/s/core/subdomain1/delete');

        self::assertSame('symfonicat_subdomain_delete', $matched['_route']);
        self::assertSame('core/subdomain1', $matched['id']);
    }
}
