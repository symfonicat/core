<?php

namespace App\Tests\Integration\Controller;

use App\Tests\Support\SymfonicatWebTestCase;

final class TextRouteTest extends SymfonicatWebTestCase
{
    public function testTextRouteIsRegisteredAndWinsOverRuntimeCatchAll(): void
    {
        $router = self::getContainer()->get('router');

        $matched = $router->match('/text');

        self::assertSame('symfonicat_text', $matched['_route']);
        self::assertSame('App\\Controller\\TextController::main', $matched['_controller']);

        $this->client()->request('GET', '/text');

        self::assertResponseIsSuccessful();
        self::assertSame('pizza', trim((string) $this->client()->getResponse()->getContent()));
    }
}
