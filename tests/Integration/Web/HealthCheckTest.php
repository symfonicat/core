<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

final class HealthCheckTest extends SymfonicatWebTestCase
{
    public function testHealthzReturnsOkForPrivateIpHost(): void
    {
        $this->setHost('172.26.32.194');
        $this->client()->request('GET', '/healthz');

        self::assertResponseIsSuccessful();
        self::assertSame('ok', (string) $this->client()->getResponse()->getContent());
    }
}
