<?php

namespace App\Tests\Integration\Command;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfony\Bundle\FrameworkBundle\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Symfonicat's bootstrap command is invoked on every container start. It must
 * therefore be idempotent: running it twice should produce identical state and
 * must not duplicate seed rows, re-attach already-attached projects, or
 * overwrite env values that already match the expected default.
 */
final class BootstrapCommandTest extends SymfonicatKernelTestCase
{
    public function testFirstRunSeedsLocalhostAndProject(): void
    {
        $exitCode = $this->runBootstrap();
        self::assertSame(0, $exitCode);

        $em = $this->entityManager();
        $em->clear();

        $localhost = $em->getRepository(Domain::class)->find('localhost');
        $exampleCom = $em->getRepository(Domain::class)->find('example.com');
        $project = $em->getRepository(Project::class)->find('project1');
        $application = $em->getRepository(Application::class)->find('test');
        $analytics = $em->getRepository(Module::class)->find('analytics');
        $color = $em->getRepository(Env::class)->find('color');

        self::assertInstanceOf(Domain::class, $localhost);
        self::assertInstanceOf(Domain::class, $exampleCom);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame('Project 1', $project->getName());
        self::assertInstanceOf(Application::class, $application);
        self::assertInstanceOf(Module::class, $analytics);
        self::assertSame('Analytics', $analytics->getName());
        self::assertInstanceOf(Env::class, $color);

        self::assertTrue(
            $exampleCom->hasProject($project),
            'bootstrap must attach project1 to example.com so subdomain routing works out of the box',
        );

        self::assertTrue(
            $application->hasModule($analytics),
            'bootstrap must attach Analytics to the test application so application routes have a module by default',
        );

        self::assertSame('blue', $this->envValueFor($localhost, 'color'));
        self::assertSame('blue', $this->envValueFor($exampleCom, 'color'));
        self::assertSame('green', $this->projectEnvValueFor($project, 'color'));
        self::assertSame('red', $this->applicationEnvValueFor($application, 'color'));
    }

    public function testSecondRunIsIdempotentAndDoesNotDuplicateRows(): void
    {
        self::assertSame(0, $this->runBootstrap());
        $firstCounts = $this->countAll();

        self::assertSame(0, $this->runBootstrap());
        $secondCounts = $this->countAll();

        self::assertSame(
            $firstCounts,
            $secondCounts,
            sprintf(
                'expected no new rows on a re-run; got %s vs %s',
                json_encode($firstCounts),
                json_encode($secondCounts),
            ),
        );
    }

    public function testSeedLocalhostCanBeOptedOut(): void
    {
        $tester = $this->makeCommandTester();
        $exitCode = $tester->execute([
            '--wait' => '0',
            '--no-seed-localhost' => true,
        ]);

        self::assertSame(0, $exitCode);
        $this->entityManager()->clear();

        self::assertSame(
            [
                'application' => 0,
                'application_env' => 0,
                'domain' => 0,
                'project' => 0,
                'env' => 0,
                'domain_env' => 0,
                'project_env' => 0,
                'module' => 0,
                'module_application' => 0,
            ],
            $this->countAll(),
            '--no-seed-localhost must skip ALL defaults, not just the localhost domain',
        );
    }

    public function testOverwritingAnExplicitEnvValueIsAllowed(): void
    {
        self::assertSame(0, $this->runBootstrap());

        // Manually tweak the localhost color so we can confirm the next bootstrap
        // brings it back into line with the canonical seed.
        $em = $this->entityManager();
        $localhost = $em->getRepository(Domain::class)->find('localhost');
        self::assertNotNull($localhost);

        foreach ($localhost->getEnv() as $row) {
            if ($row->getEnv()?->getId() === 'color') {
                $row->setValue('hot-pink');
                break;
            }
        }
        $em->flush();

        self::assertSame('hot-pink', $this->envValueFor($localhost, 'color'));

        self::assertSame(0, $this->runBootstrap());
        $em->clear();

        $localhostAfter = $em->getRepository(Domain::class)->find('localhost');
        self::assertNotNull($localhostAfter);
        self::assertSame(
            'blue',
            $this->envValueFor($localhostAfter, 'color'),
            'bootstrap must restore the canonical localhost color value on subsequent runs',
        );
    }

    private function runBootstrap(): int
    {
        return $this->makeCommandTester()->execute(['--wait' => '0']);
    }

    private function makeCommandTester(): CommandTester
    {
        $application = new ConsoleApplication(self::$kernel);
        $application->setAutoExit(false);
        $command = $application->find('symfonicat:bootstrap');

        return new CommandTester($command);
    }

    /**
     * @return array<string, int>
     */
    private function countAll(): array
    {
        $em = $this->entityManager();
        $em->clear();

        return [
            'application' => $this->countTable('symfonicat_application'),
            'application_env' => $this->countTable('symfonicat_application_env'),
            'domain' => $this->countTable('symfonicat_domain'),
            'project' => $this->countTable('symfonicat_project'),
            'env' => $this->countTable('symfonicat_env'),
            'domain_env' => $this->countTable('symfonicat_domain_env'),
            'project_env' => $this->countTable('symfonicat_project_env'),
            'module' => $this->countTable('symfonicat_module'),
            'module_application' => $this->countTable('symfonicat_module_application'),
        ];
    }

    private function countTable(string $table): int
    {
        return (int) $this->entityManager()
            ->getConnection()
            ->fetchOne(sprintf('SELECT COUNT(*) FROM %s', $table));
    }

    private function envValueFor(Domain $domain, string $envId): ?string
    {
        foreach ($domain->getEnv() as $row) {
            if ($row->getEnv()?->getId() === $envId) {
                return $row->getValue();
            }
        }

        return null;
    }

    private function projectEnvValueFor(Project $project, string $envId): ?string
    {
        foreach ($project->getEnv() as $row) {
            if ($row->getEnv()?->getId() === $envId) {
                return $row->getValue();
            }
        }

        return null;
    }

    private function applicationEnvValueFor(Application $application, string $envId): ?string
    {
        foreach ($application->getEnv() as $row) {
            if ($row->getEnv()?->getId() === $envId) {
                return $row->getValue();
            }
        }

        return null;
    }
}
