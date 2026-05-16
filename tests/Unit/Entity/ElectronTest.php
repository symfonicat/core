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
        $project = (new Project())
            ->setId('core/project1');

        $electron = (new Electron())
            ->setType(Electron::TYPE_PROJECT)
            ->setProject($project);

        self::assertNull($electron->projectTargetId());
        self::assertNull($electron->getTargetId());
    }

    public function testProjectTargetIdUsesProjectAndDomainIds(): void
    {
        $project = (new Project())
            ->setId('core/project1');
        $domain = (new Domain())
            ->setId('example.com');

        $electron = (new Electron())
            ->setType(Electron::TYPE_PROJECT)
            ->setProject($project)
            ->setDomain($domain);

        self::assertSame('project1.example.com', $electron->projectTargetId());
        self::assertSame('project1.example.com', $electron->getTargetId());
    }
}
