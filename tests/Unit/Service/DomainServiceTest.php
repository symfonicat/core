<?php

namespace App\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\PackageDiscoveryService;
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

    private function makeService(string $host): DomainService
    {
        $subdomainDir = dirname(__DIR__, 3);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'GET', [], [], [], [
            'HTTP_HOST' => $host,
        ]));

        $domainRepository = $this->createStub(DomainRepository::class);
        $domainRepository->method('findOneByHost')->willReturn(null);

        $entityManager = $this->createStub(EntityManagerInterface::class);
        $packageDiscoveryService = new PackageDiscoveryService($subdomainDir);
        $runtimeConfig = new RuntimeConfig($subdomainDir);

        return new DomainService(
            $subdomainDir,
            $requestStack,
            $domainRepository,
            $entityManager,
            $packageDiscoveryService,
            $runtimeConfig,
        );
    }
}
