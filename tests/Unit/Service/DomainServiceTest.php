<?php

namespace App\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\RuntimeConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class DomainServiceTest extends TestCase
{
    public function testHostReturnsNullForIpv4Literal(): void
    {
        $service = $this->makeService('172.26.32.194');

        self::assertNull($service->host());
        self::assertNull($service->load());
    }

    public function testHostReturnsRegistrableDomainForLocalhostStyleNames(): void
    {
        $service = $this->makeService('subdomain.example.com');

        self::assertSame('example.com', $service->host());
    }

    public function testLoadUsesYamlForPublicRequests(): void
    {
        $service = $this->makeService('example.com', '/');

        self::assertSame('example.com', $service->load()?->getTld());
    }

    public function testLoadUsesDatabaseForCoreRoutes(): void
    {
        $subdomainDir = dirname(__DIR__, 3);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/core/d/example.com', 'GET', [], [], [], [
            'HTTP_HOST' => 'example.com',
        ]));

        $domain = (new Domain())->setId('example.com');

        $domainRepository = $this->createMock(DomainRepository::class);
        $domainRepository->expects(self::once())
            ->method('findOneByHost')
            ->with('example.com')
            ->willReturn($domain);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $runtimeConfig = new RuntimeConfig($subdomainDir);

        $service = new DomainService(
            $requestStack,
            $domainRepository,
            $entityManager,
            $runtimeConfig,
        );

        self::assertSame('example.com', $service->load()?->getTld());
    }

    private function makeService(string $host, string $path = '/'): DomainService
    {
        $subdomainDir = dirname(__DIR__, 3);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create($path, 'GET', [], [], [], [
            'HTTP_HOST' => $host,
        ]));

        $domainRepository = $this->createMock(DomainRepository::class);
        $domainRepository->expects(self::never())->method('findOneByHost');

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $runtimeConfig = new RuntimeConfig($subdomainDir);

        return new DomainService(
            $requestStack,
            $domainRepository,
            $entityManager,
            $runtimeConfig,
        );
    }
}
