<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Service\AffixService;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\RuntimeConfig;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Doctrine\ORM\EntityManagerInterface;

final class AffixServiceTest extends TestCase
{
    public function testGetAffixesReturnsEmptyArrayForIpv4Literal(): void
    {
        $service = $this->makeService('172.26.16.99');

        self::assertSame([], $service->getAffixesRaw());
        self::assertSame([], $service->getAffixes());
    }

    private function makeService(string $host): AffixService
    {
        $subdomainDir = dirname(__DIR__, 3);

        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/', 'GET', [], [], [], [
            'HTTP_HOST' => $host,
        ]));

        $domainRepository = $this->createStub(DomainRepository::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $runtimeConfig = new RuntimeConfig($subdomainDir);
        $domainService = new DomainService(
            $subdomainDir,
            $requestStack,
            $domainRepository,
            $entityManager,
            $runtimeConfig,
        );

        return new AffixService($subdomainDir, $requestStack, new NullLogger(), $domainService);
    }
}
