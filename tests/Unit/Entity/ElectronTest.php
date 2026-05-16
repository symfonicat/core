<?php

namespace App\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;

final class ElectronTest extends TestCase
{
    public function testIdIsPlainAndNotVendorScoped(): void
    {
        $electron = (new Electron())->setId('example-test');

        self::assertSame('example-test', $electron->getId());
        self::assertSame('example-test', $electron->getId(false));
    }

    public function testProjectTargetIdRequiresBothProjectAndDomain(): void
    {
        $subdomain = (new Project())
            ->setId('core/subdomain1');

        $electron = (new Electron())
            ->setType(Electron::TYPE_PROJECT)
            ->setProject($subdomain);

        self::assertNull($electron->subdomainTargetId());
        self::assertNull($electron->getTargetId());
    }

    public function testProjectTargetIdUsesProjectAndDomainIds(): void
    {
        $subdomain = (new Project())
            ->setId('core/subdomain1');
        $domain = (new Domain())
            ->setId('example.com');

        $electron = (new Electron())
            ->setType(Electron::TYPE_PROJECT)
            ->setProject($subdomain)
            ->setDomain($domain);

        self::assertSame('subdomain1.example.com', $electron->subdomainTargetId());
        self::assertSame('subdomain1.example.com', $electron->getTargetId());
    }
}
