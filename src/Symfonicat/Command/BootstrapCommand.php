<?php

namespace Symfonicat\Command;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\EnvRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:bootstrap',
    description: 'Wait for the database, synchronize the schema, and seed local development defaults.',
)]
final class BootstrapCommand extends Command
{
    private const BOOTSTRAP_LOCK_NAMESPACE = 1398361414; // "SYMF"
    private const BOOTSTRAP_LOCK_KEY = 1112493908; // "BOOT"

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DomainRepository $domainRepository,
        private readonly EnvRepository $envRepository,
        private readonly ProjectRepository $projectRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Seconds to wait for the database to become available.', '60')
            ->addOption('seed-localhost', null, InputOption::VALUE_NEGATABLE, 'Seed local development defaults.', true)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $waitSeconds = max(0, (int) $input->getOption('wait'));

        if (!$this->waitForDatabase($waitSeconds)) {
            $io->error(sprintf('Database did not become available within %d seconds.', $waitSeconds));

            return Command::FAILURE;
        }

        $this->runWithBootstrapLock(function () use ($input, $io): void {
            $this->synchronizeSchema();
            $io->success('Database schema is synchronized.');

            if ((bool) $input->getOption('seed-localhost')) {
                $seeded = $this->seedLocalDefaults();
                $io->success($seeded);
            }
        });

        return Command::SUCCESS;
    }

    private function waitForDatabase(int $waitSeconds): bool
    {
        $connection = $this->entityManager->getConnection();
        $deadline = microtime(true) + $waitSeconds;

        do {
            try {
                $connection->executeQuery('SELECT 1');

                return true;
            } catch (\Throwable) {
                usleep(500_000);
            }
        } while (microtime(true) < $deadline);

        return false;
    }

    private function synchronizeSchema(): void
    {
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        if ($metadata === []) {
            return;
        }

        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->updateSchema($metadata, true);
    }

    private function runWithBootstrapLock(callable $callback): void
    {
        $connection = $this->entityManager->getConnection();
        if (!$connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $callback();

            return;
        }

        $params = [
            'namespace' => self::BOOTSTRAP_LOCK_NAMESPACE,
            'key' => self::BOOTSTRAP_LOCK_KEY,
        ];
        $types = [
            'namespace' => ParameterType::INTEGER,
            'key' => ParameterType::INTEGER,
        ];

        $connection->executeQuery(
            'SELECT pg_advisory_lock(:namespace, :key)',
            $params,
            $types,
        )->free();

        try {
            $callback();
        } finally {
            $connection->executeQuery(
                'SELECT pg_advisory_unlock(:namespace, :key)',
                $params,
                $types,
            )->free();
        }
    }

    private function seedLocalDefaults(): string
    {
        $env = $this->envRepository->find('color');
        $createdEnv = false;

        if (!$env instanceof Env) {
            $env = (new Env())
                ->setId('color');

            $this->entityManager->persist($env);
            $createdEnv = true;
        }

        $localhost = $this->domainRepository->find('localhost');
        $createdLocalhost = false;
        if (!$localhost instanceof Domain) {
            $localhost = (new Domain())
                ->setId('localhost');

            $this->entityManager->persist($localhost);
            $createdLocalhost = true;
        }

        $exampleDomain = $this->domainRepository->find('example.com');
        $createdExampleDomain = false;
        if (!$exampleDomain instanceof Domain) {
            $exampleDomain = (new Domain())
                ->setId('example.com');

            $this->entityManager->persist($exampleDomain);
            $createdExampleDomain = true;
        }

        $project = $this->projectRepository->find('project1');
        $createdProject = false;
        $updatedProject = false;

        if (!$project instanceof Project) {
            $project = (new Project())
                ->setId('project1')
                ->setName('Project 1');

            $this->entityManager->persist($project);
            $createdProject = true;
        } else {
            if ($project->getName() !== 'Project 1') {
                $project->setName('Project 1');
                $updatedProject = true;
            }
        }

        $attachedProjectToExample = $this->attachProjectToDomain($exampleDomain, $project);

        $localhostColor = $this->ensureDomainEnvValue($localhost, $env, 'blue');
        $exampleColor = $this->ensureDomainEnvValue($exampleDomain, $env, 'blue');
        $projectColor = $this->ensureProjectEnvValue($project, $env, 'green');

        $this->entityManager->flush();

        if (
            $createdEnv
            || $createdLocalhost
            || $createdExampleDomain
            || $createdProject
            || $updatedProject
            || $attachedProjectToExample
            || $localhostColor !== 'unchanged'
            || $exampleColor !== 'unchanged'
            || $projectColor !== 'unchanged'
        ) {
            $messages = [];

            if ($createdLocalhost) {
                $messages[] = 'seeded localhost domain';
            } else {
                $messages[] = 'localhost domain already present';
            }

            if ($createdExampleDomain) {
                $messages[] = 'seeded example.com domain';
            } else {
                $messages[] = 'example.com domain already present';
            }

            if ($createdEnv) {
                $messages[] = 'seeded color env';
            } else {
                $messages[] = 'color env already present';
            }

            if ($createdProject) {
                $messages[] = 'seeded Project 1 project';
            } elseif ($updatedProject) {
                $messages[] = 'updated Project 1 project';
            } else {
                $messages[] = 'Project 1 project already present';
            }

            if ($attachedProjectToExample) {
                $messages[] = 'attached Project 1 to example.com';
            } else {
                $messages[] = 'Project 1 already attached to example.com';
            }

            if ($localhostColor === 'created') {
                $messages[] = 'seeded localhost color env value';
            } elseif ($localhostColor === 'updated') {
                $messages[] = 'updated localhost color env value';
            } else {
                $messages[] = 'localhost color env value already present';
            }

            if ($exampleColor === 'created') {
                $messages[] = 'seeded example.com color env value';
            } elseif ($exampleColor === 'updated') {
                $messages[] = 'updated example.com color env value';
            } else {
                $messages[] = 'example.com color env value already present';
            }

            if ($projectColor === 'created') {
                $messages[] = 'seeded Project 1 color env value';
            } elseif ($projectColor === 'updated') {
                $messages[] = 'updated Project 1 color env value';
            } else {
                $messages[] = 'Project 1 color env value already present';
            }

            return implode('; ', $messages).'.';
        }

        return 'Local development defaults already present.';
    }

    private function attachProjectToDomain(Domain $domain, Project $project): bool
    {
        if ($domain->hasProject($project)) {
            return false;
        }

        $domain->addProject($project);

        return true;
    }

    private function ensureDomainEnvValue(Domain $domain, Env $env, string $value): string
    {
        foreach ($domain->getEnv() as $item) {
            if ($item->getEnv()?->getId() !== $env->getId()) {
                continue;
            }

            if ($item->getValue() === $value) {
                return 'unchanged';
            }

            $item->setValue($value);

            return 'updated';
        }

        $domainEnv = (new DomainEnv())
            ->setEnv($env)
            ->setValue($value);

        $domain->addEnv($domainEnv);
        $this->entityManager->persist($domainEnv);

        return 'created';
    }

    private function ensureProjectEnvValue(Project $project, Env $env, string $value): string
    {
        foreach ($project->getEnv() as $item) {
            if ($item->getEnv()?->getId() !== $env->getId()) {
                continue;
            }

            if ($item->getValue() === $value) {
                return 'unchanged';
            }

            $item->setValue($value);

            return 'updated';
        }

        $projectEnv = (new ProjectEnv())
            ->setEnv($env)
            ->setValue($value);

        $project->addEnv($projectEnv);
        $this->entityManager->persist($projectEnv);

        return 'created';
    }
}
