<?php

namespace Symfonicat\Command;

use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\ElectronEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Entity\RoutingRule;
use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ElectronRepository;
use Symfonicat\Repository\EnvRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfonicat\Repository\RoutingRuleRepository;
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
        private readonly ApplicationRepository $applicationRepository,
        private readonly DomainRepository $domainRepository,
        private readonly ElectronRepository $electronRepository,
        private readonly EnvRepository $envRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly RoutingRuleRepository $routingRuleRepository,
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
        $envParent = $this->entityManager->find(EnvParent::class, 'colors');
        $createdEnvParent = false;

        if (!$envParent instanceof EnvParent) {
            $envParent = (new EnvParent())
                ->setId('colors');

            $this->entityManager->persist($envParent);
            $createdEnvParent = true;
        }

        $env = $this->envRepository->find('primary');
        $createdEnv = false;
        $updatedEnv = false;

        if (!$env instanceof Env) {
            $env = (new Env())
                ->setId('primary')
                ->setEnvParent($envParent);

            $this->entityManager->persist($env);
            $createdEnv = true;
        } elseif ($env->getEnvParent()?->getId() !== $envParent->getId()) {
            $env->setEnvParent($envParent);
            $updatedEnv = true;
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
        [$exampleElectron, $createdExampleElectron, $updatedExampleElectron] = $this->ensureExampleDomainElectron($exampleDomain);

        $localhostColor = $this->ensureDomainEnvValue($localhost, $env, 'blue');
        $exampleColor = $this->ensureDomainEnvValue($exampleDomain, $env, 'blue');
        $projectColor = $this->ensureProjectEnvValue($project, $env, 'green');
        $projectColorParentUpdated = $this->ensureProjectEnvParent($project, $env, $envParent);
        [$analyticsModule, $createdAnalyticsModule, $updatedAnalyticsModule] = $this->ensureAnalyticsModule();
        [$application, $createdApplication] = $this->ensureTestApplication();
        $attachedAnalyticsToApplication = $this->attachModuleToApplication($application, $analyticsModule);
        $attachedAnalyticsToProject = $this->attachModuleToProject($project, $analyticsModule);
        $attachedAnalyticsToExample = $this->attachModuleToDomain($exampleDomain, $analyticsModule);
        $attachedAnalyticsToLocalhost = $this->attachModuleToDomain($localhost, $analyticsModule);
        $applicationColor = $this->ensureApplicationEnvValue($application, $env, 'red');
        $electronColor = $this->ensureElectronEnvValue($exampleElectron, $env, 'yellow');
        $createdApplicationRoutingRule = $this->ensureTestApplicationRoutingRule($application);

        $this->entityManager->flush();

        if (
            $createdEnvParent
            || $createdEnv
            || $updatedEnv
            || $createdLocalhost
            || $createdExampleDomain
            || $createdProject
            || $updatedProject
            || $attachedProjectToExample
            || $createdExampleElectron
            || $updatedExampleElectron
            || $createdAnalyticsModule
            || $updatedAnalyticsModule
            || $createdApplication
            || $attachedAnalyticsToApplication
            || $attachedAnalyticsToProject
            || $attachedAnalyticsToExample
            || $attachedAnalyticsToLocalhost
            || $localhostColor !== 'unchanged'
            || $exampleColor !== 'unchanged'
            || $projectColor !== 'unchanged'
            || $projectColorParentUpdated
            || $applicationColor !== 'unchanged'
            || $electronColor !== 'unchanged'
            || $createdApplicationRoutingRule
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

            if ($createdEnvParent) {
                $messages[] = 'seeded colors env parent';
            } else {
                $messages[] = 'colors env parent already present';
            }

            if ($createdEnv) {
                $messages[] = 'seeded primary env';
            } elseif ($updatedEnv) {
                $messages[] = 'updated primary env';
            } else {
                $messages[] = 'primary env already present';
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

            if ($createdExampleElectron) {
                $messages[] = 'seeded Example Test electron';
            } elseif ($updatedExampleElectron) {
                $messages[] = 'updated Example Test electron';
            } else {
                $messages[] = 'Example Test electron already present';
            }

            if ($createdAnalyticsModule) {
                $messages[] = 'seeded Analytics module';
            } elseif ($updatedAnalyticsModule) {
                $messages[] = 'updated Analytics module';
            } else {
                $messages[] = 'Analytics module already present';
            }

            if ($createdApplication) {
                $messages[] = 'seeded test application';
            } else {
                $messages[] = 'test application already present';
            }

            if ($attachedAnalyticsToApplication) {
                $messages[] = 'attached Analytics module to test application';
            } else {
                $messages[] = 'Analytics module already attached to test application';
            }

            if ($attachedAnalyticsToProject) {
                $messages[] = 'attached Analytics module to Project 1';
            } else {
                $messages[] = 'Analytics module already attached to Project 1';
            }

            if ($attachedAnalyticsToExample) {
                $messages[] = 'attached Analytics module to example.com domain';
            } else {
                $messages[] = 'Analytics module already attached to example.com domain';
            }

            if ($attachedAnalyticsToLocalhost) {
                $messages[] = 'attached Analytics module to localhost domain';
            } else {
                $messages[] = 'Analytics module already attached to localhost domain';
            }

            if ($localhostColor === 'created') {
                $messages[] = 'seeded localhost colors.primary env value';
            } elseif ($localhostColor === 'updated') {
                $messages[] = 'updated localhost colors.primary env value';
            } else {
                $messages[] = 'localhost colors.primary env value already present';
            }

            if ($exampleColor === 'created') {
                $messages[] = 'seeded example.com colors.primary env value';
            } elseif ($exampleColor === 'updated') {
                $messages[] = 'updated example.com colors.primary env value';
            } else {
                $messages[] = 'example.com colors.primary env value already present';
            }

            if ($projectColor === 'created') {
                $messages[] = 'seeded Project 1 colors.primary env value';
            } elseif ($projectColor === 'updated') {
                $messages[] = 'updated Project 1 colors.primary env value';
            } else {
                $messages[] = 'Project 1 colors.primary env value already present';
            }

            if ($projectColorParentUpdated) {
                $messages[] = 'updated Project 1 colors.primary env parent';
            }

            if ($applicationColor === 'created') {
                $messages[] = 'seeded test application colors.primary env value';
            } elseif ($applicationColor === 'updated') {
                $messages[] = 'updated test application colors.primary env value';
            } else {
                $messages[] = 'test application colors.primary env value already present';
            }

            if ($electronColor === 'created') {
                $messages[] = 'seeded Example Test electron colors.primary env value';
            } elseif ($electronColor === 'updated') {
                $messages[] = 'updated Example Test electron colors.primary env value';
            } else {
                $messages[] = 'Example Test electron colors.primary env value already present';
            }

            if ($createdApplicationRoutingRule) {
                $messages[] = 'seeded test application routing rule';
            } else {
                $messages[] = 'test application routing rule already present';
            }

            return implode("\n", $messages).'.';
        }

        return 'Local development defaults already present.';
    }

    /**
     * @return array{0: Module, 1: bool, 2: bool}
     */
    private function ensureAnalyticsModule(): array
    {
        $module = $this->moduleRepository->find('analytics');
        $created = false;
        $updated = false;

        if (!$module instanceof Module) {
            $module = (new Module())
                ->setId('analytics')
                ->setName('Analytics');

            $this->entityManager->persist($module);
            $created = true;
        } elseif ($module->getName() !== 'Analytics') {
            $module->setName('Analytics');
            $updated = true;
        }

        return [$module, $created, $updated];
    }

    /**
     * @return array{0: Application, 1: bool}
     */
    private function ensureTestApplication(): array
    {
        $application = $this->applicationRepository->find('test');
        $created = false;

        if (!$application instanceof Application) {
            $application = (new Application())
                ->setId('test');

            $this->entityManager->persist($application);
            $created = true;
        }

        return [$application, $created];
    }

    /**
     * @return array{0: Electron, 1: bool, 2: bool}
     */
    private function ensureExampleDomainElectron(Domain $domain): array
    {
        $electron = $this->electronRepository->findOneForDomain($domain);
        $created = false;
        $updated = false;

        if (!$electron instanceof Electron) {
            $electron = (new Electron())
                ->setType(Electron::TYPE_DOMAIN)
                ->setDomain($domain);

            $this->entityManager->persist($electron);
            $created = true;
        }

        if ($electron->getName() !== 'Example Test') {
            $electron->setName('Example Test');
            $updated = true;
        }

        if ($electron->getFavicon() !== 'electron/favicon/domain/example.com.png') {
            $electron->setFavicon('electron/favicon/domain/example.com.png');
            $updated = true;
        }

        if (!$electron->isDomainType() || $electron->getDomain()?->getId() !== $domain->getId()) {
            $electron
                ->setType(Electron::TYPE_DOMAIN)
                ->setDomain($domain)
                ->setProject(null)
                ->setApplication(null);
            $updated = true;
        }

        return [$electron, $created, $updated];
    }

    private function attachProjectToDomain(Domain $domain, Project $project): bool
    {
        if ($domain->hasProject($project)) {
            return false;
        }

        $domain->addProject($project);

        return true;
    }

    private function attachModuleToApplication(Application $application, Module $module): bool
    {
        if ($application->hasModule($module)) {
            return false;
        }

        $application->addModule($module);

        return true;
    }

    private function attachModuleToProject(Project $project, Module $module): bool
    {
        if ($project->hasModule($module)) {
            return false;
        }

        $project->addModule($module);

        return true;
    }

    private function attachModuleToDomain(Domain $domain, Module $module): bool
    {
        if ($domain->hasModule($module)) {
            return false;
        }

        $domain->addModule($module);

        return true;
    }

    private function ensureTestApplicationRoutingRule(Application $application): bool
    {
        $arguments = ['symfonicat', '*', 'test*'];

        foreach ($this->routingRuleRepository->findTypeApplication() as $rule) {
            if ($rule->getApplication()?->getId() === $application->getId() && $rule->getArguments() === $arguments) {
                return false;
            }
        }

        $rule = (new RoutingRule())
            ->setType(RoutingRule::TYPE_APPLICATION)
            ->setApplication($application)
            ->setApplicationType(RoutingRule::APPLICATION_TYPE_ARGUMENTS)
            ->setArguments($arguments);

        $this->entityManager->persist($rule);

        return true;
    }

    private function ensureApplicationEnvValue(Application $application, Env $env, string $value): string
    {
        foreach ($application->getEnv() as $item) {
            if ($item->getEnv()?->getId() !== $env->getId()) {
                continue;
            }

            if ($item->getValue() === $value) {
                return 'unchanged';
            }

            $item->setValue($value);

            return 'updated';
        }

        $applicationEnv = (new ApplicationEnv())
            ->setEnv($env)
            ->setValue($value);

        $application->addEnv($applicationEnv);
        $this->entityManager->persist($applicationEnv);

        return 'created';
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

    private function ensureElectronEnvValue(Electron $electron, Env $env, string $value): string
    {
        foreach ($electron->getEnv() as $item) {
            if ($item->getEnv()?->getId() !== $env->getId()) {
                continue;
            }

            if ($item->getValue() === $value) {
                return 'unchanged';
            }

            $item->setValue($value);

            return 'updated';
        }

        $electronEnv = (new ElectronEnv())
            ->setEnv($env)
            ->setValue($value);

        $electron->addEnv($electronEnv);
        $this->entityManager->persist($electronEnv);

        return 'created';
    }

    private function ensureProjectEnvParent(Project $project, Env $env, EnvParent $envParent): bool
    {
        $updated = false;

        foreach ($project->getEnv() as $item) {
            if ($item->getEnv()?->getId() !== $env->getId()) {
                continue;
            }

            if ($item->getEnv()?->getEnvParent()?->getId() === $envParent->getId()) {
                continue;
            }

            $item->getEnv()?->setEnvParent($envParent);
            $updated = true;
        }

        return $updated;
    }
}
