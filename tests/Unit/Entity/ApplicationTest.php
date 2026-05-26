<?php

namespace App\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Subdomain;

final class ApplicationTest extends TestCase
{
    public function testIdIsPlainAndNotVendorScoped(): void
    {
        $application = (new Application())->setId('example-test');

        self::assertSame('example-test', $application->getId());
        self::assertSame('example-test', $application->getId(false));
    }

    public function testSubdomainTargetIdRequiresBothSubdomainAndDomain(): void
    {
        $subdomain = (new Subdomain())
            ->setId('core/subdomain1');

        $application = (new Application())
            ->setType(Application::TYPE_SUBDOMAIN)
            ->setSubdomain($subdomain);

        self::assertNull($application->subdomainTargetId());
        self::assertNull($application->getTargetId());
    }

    public function testSubdomainTargetIdUsesSubdomainAndDomainIds(): void
    {
        $subdomain = (new Subdomain())
            ->setId('core/subdomain1');
        $domain = (new Domain())
            ->setId('example.com');

        $application = (new Application())
            ->setType(Application::TYPE_SUBDOMAIN)
            ->setSubdomain($subdomain)
            ->setDomain($domain);

        self::assertSame('subdomain1.example.com', $application->subdomainTargetId());
        self::assertSame('subdomain1.example.com', $application->getTargetId());
    }

    public function testEndpointSelectionTakesPrecedenceForDerivedApplicationType(): void
    {
        $domain = (new Domain())
            ->setId('example.com');
        $endpoint = (new \Symfonicat\Entity\Endpoint())
            ->setId('core/test');

        $application = (new Application())
            ->setDomain($domain)
            ->setEndpoint($endpoint)
            ->setType(Application::TYPE_DOMAIN);

        self::assertSame(Application::TYPE_ENDPOINT, $application->getType());
        self::assertTrue($application->isEndpointType());
        self::assertSame('core/test', $application->getTargetId());
    }
}
