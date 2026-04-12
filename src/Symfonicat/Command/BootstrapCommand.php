<?php

namespace Symfonicat\Command;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Module;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\EnvRepository;
use Symfonicat\Repository\ModuleRepository;
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
        private readonly ModuleRepository $moduleRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('wait', null, InputOption::VALUE_REQUIRED, 'Seconds to wait for the database to become available.', '60')
            ->addOption('seed-localhost', null, InputOption::VALUE_NEGATABLE, 'Seed the localhost domain row.', true)
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
        $domain = $this->domainRepository->find('localhost');
        $createdDomain = false;

        if (!$domain instanceof Domain) {
            $domain = (new Domain())
                ->setId('localhost');

            $this->entityManager->persist($domain);
            $createdDomain = true;
        }

        $module = $this->moduleRepository->findOneBySlug('analytics');
        $createdModule = false;

        if (!$module instanceof Module) {
            $module = (new Module())
                ->setSlug('analytics')
                ->setName('Analytics');

            $this->entityManager->persist($module);
            $createdModule = true;
        }

        $env = $this->envRepository->find('color');
        $createdEnv = false;

        if (!$env instanceof Env) {
            $env = (new Env())
                ->setId('color');

            $this->entityManager->persist($env);
            $createdEnv = true;
        }

        $attachedModule = false;
        if (!$domain->hasModule($module)) {
            $domain->addModule($module);
            $attachedModule = true;
        }

        $domainEnv = null;
        foreach ($domain->getEnv() as $item) {
            if ($item->getEnv()?->getId() === 'color') {
                $domainEnv = $item;

                break;
            }
        }

        $createdDomainEnv = false;
        $updatedDomainEnv = false;

        if (!$domainEnv instanceof DomainEnv) {
            $domainEnv = (new DomainEnv())
                ->setEnv($env)
                ->setValue('blue');

            $domain->addEnv($domainEnv);
            $this->entityManager->persist($domainEnv);
            $createdDomainEnv = true;
        } elseif ($domainEnv->getValue() !== 'blue') {
            $domainEnv->setValue('blue');
            $updatedDomainEnv = true;
        }

        $this->entityManager->flush();

        if ($createdDomain || $createdModule || $createdEnv || $attachedModule || $createdDomainEnv || $updatedDomainEnv) {
            $messages = [];

            if ($createdDomain) {
                $messages[] = 'Seeded localhost domain';
            } else {
                $messages[] = 'Localhost domain already present';
            }

            if ($createdModule) {
                $messages[] = 'seeded Analytics module';
            } else {
                $messages[] = 'Analytics module already present';
            }

            if ($createdEnv) {
                $messages[] = 'seeded color env';
            } else {
                $messages[] = 'color env already present';
            }

            if ($attachedModule) {
                $messages[] = 'attached Analytics to localhost';
            } else {
                $messages[] = 'Analytics already attached to localhost';
            }

            if ($createdDomainEnv) {
                $messages[] = 'seeded localhost color env value';
            } elseif ($updatedDomainEnv) {
                $messages[] = 'updated localhost color env value';
            } else {
                $messages[] = 'localhost color env value already present';
            }

            return implode('; ', $messages).'.';
        }

        return 'Local development defaults already present.';
    }
}
