<?php

namespace App\Tests\Integration\Command;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Module;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * End-to-end coverage of the installed-package ⇆ database module sync command.
 *
 * The command is the contract between installed configured-vendor packages
 * and the symfonicat_module table (runtime-authoritative). These tests make
 * sure that contract holds for the three transitions that matter in practice:
 *   - package-provided module with no matching row -> row is created
 *   - package metadata change                      -> row is updated
 *   - row with no backing package                  -> row is deleted (or blocked
 *                                                     if rows still reference it)
 */
final class SchemaUpdateCommandTest extends SymfonicatKernelTestCase
{
    public function testCreatesRowsForFilesystemModulesThatAreMissingInDatabase(): void
    {
        $tester = $this->runCommand();

        self::assertSame(0, $tester->getStatusCode());

        $em = $this->entityManager();
        $em->clear();

        $analytics = $em->getRepository(Module::class)->find('symfonicat/analytics/main');
        self::assertInstanceOf(Module::class, $analytics);
        self::assertSame('analytics', $analytics->getPackage());
    }

    public function testUpdatesRowPackageWhenPackageMetadataDivergesFromDatabase(): void
    {
        $this->createModule('symfonicat/analytics/main', 'stale-package');

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();
        $analytics = $this->entityManager()->getRepository(Module::class)->find('symfonicat/analytics/main');

        self::assertInstanceOf(Module::class, $analytics);
        self::assertSame('analytics', $analytics->getPackage());
        self::assertStringContainsString('Updated modules', $tester->getDisplay());
    }

    public function testRemovesUnreferencedRowsThatNoLongerExistOnDisk(): void
    {
        // An orphan module exists in the DB with no corresponding installed configured-vendor package module.
        $this->createModule('orphan');

        $tester = $this->runCommand();
        self::assertSame(0, $tester->getStatusCode());

        $this->entityManager()->clear();
        self::assertNull(
            $this->entityManager()->getRepository(Module::class)->find('core/orphan'),
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
        // Schema update now requires confirmation when creating domain/application/project rows.
        $tester->setInputs(['yes', 'yes', 'yes']);
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
