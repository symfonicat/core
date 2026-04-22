<?php

namespace App\Tests\Integration\Command;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Module;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end coverage of the filesystem ⇆ database module sync command.
 *
 * The command is the contract between assets/modules/* (developer-authored)
 * and the symfonicat_module table (runtime-authoritative). These tests make
 * sure that contract holds for the three transitions that matter in practice:
 *   - filesystem module with no matching row -> row is created
 *   - package.json name change              -> row is updated
 *   - row with no filesystem module         -> row is deleted (or blocked if
 *                                               rows still reference it)
 */
final class SchemaUpdateCommandTest extends SymfonicatKernelTestCase
{
    public function testCreatesRowsForFilesystemModulesThatAreMissingInDatabase(): void
    {
        $tester = $this->runCommand();

        self::assertSame(0, $tester->getStatusCode());

        $em = $this->entityManager();
        $em->clear();

        $analytics = $em->getRepository(Module::class)->find('analytics');
        self::assertInstanceOf(Module::class, $analytics);
        self::assertSame('Analytics', $analytics->getName());
    }

    public function testUpdatesRowNameWhenPackageJsonNameDivergesFromDatabase(): void
    {
        $this->createModule('analytics', 'Stale Old Name');

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();
        $analytics = $this->entityManager()->getRepository(Module::class)->find('analytics');

        self::assertInstanceOf(Module::class, $analytics);
        self::assertSame('Analytics', $analytics->getName(), 'package.json "name" should win over the database row');
        self::assertStringContainsString('Updated modules', $tester->getDisplay());
    }

    public function testRemovesUnreferencedRowsThatNoLongerExistOnDisk(): void
    {
        // An orphan module — exists in the DB, no corresponding assets/modules/* dir.
        $this->createModule('orphan', 'Orphan Module');

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();
        self::assertNull(
            $this->entityManager()->getRepository(Module::class)->find('orphan'),
            'an orphan module with no references should be removed without prompting',
        );
    }

    public function testIsIdempotentOnReruns(): void
    {
        self::assertSame(0, $this->runCommand()->getStatusCode());
        $firstCount = $this->countModules();

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());
        self::assertSame($firstCount, $this->countModules());
        self::assertStringContainsString('already match', $tester->getDisplay());
    }

    private function runCommand(): CommandTester
    {
        $application = new Application(self::$kernel);
        $application->setAutoExit(false);
        $command = $application->find('symfonicat:schema:update');
        $tester = new CommandTester($command);
        // Schema update now requires confirmation when creating application/project rows.
        $tester->setInputs(['yes', 'yes']);
        $tester->execute([], ['interactive' => true]);

        return $tester;
    }

    private function countModules(): int
    {
        return (int) $this->entityManager()
            ->getConnection()
            ->fetchOne('SELECT COUNT(*) FROM symfonicat_module');
    }
}
