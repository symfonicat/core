<?php

namespace App\Tests\Unit\EventSubscriber;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Contract\AdminYamlDumper;
use Symfonicat\EventSubscriber\AdminYamlDumpSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AdminYamlDumpSubscriberTest extends TestCase
{
    public function testDumpsYamlAfterAdminFlushWithTrackedEntityChanges(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/admin/a/create'));

        $adminYaml = $this->createMock(AdminYamlDumper::class);
        $adminYaml->expects(self::once())->method('dump');

        $subscriber = new AdminYamlDumpSubscriber($requestStack, $adminYaml);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([(new Application())->setId('example-test')]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $subscriber->onFlush(new OnFlushEventArgs($entityManager));
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));
    }

    public function testSkipsNonAdminRequests(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/docs'));

        $adminYaml = $this->createMock(AdminYamlDumper::class);
        $adminYaml->expects(self::never())->method('dump');

        $subscriber = new AdminYamlDumpSubscriber($requestStack, $adminYaml);
        $unitOfWork = $this->createMock(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([(new Application())->setId('example-test')]);
        $unitOfWork->method('getScheduledEntityUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledEntityDeletions')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionUpdates')->willReturn([]);
        $unitOfWork->method('getScheduledCollectionDeletions')->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        $subscriber->onFlush(new OnFlushEventArgs($entityManager));
        $subscriber->postFlush(new PostFlushEventArgs($entityManager));
    }
}
