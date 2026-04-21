<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\ApplicationRepository;
use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:webpack',
    description: 'Output application, domain, project, and module data for webpack.',
)]
final class WebpackModulesDataCommand extends Command
{
    public function __construct(
        private readonly ApplicationRepository $applicationRepository,
        private readonly DomainRepository $domainRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly ProjectRepository $projectRepository,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln(json_encode([
            'applications' => $this->idsFromRepositoryOrAssets(
                fn (): array => $this->applicationRepository->findAll(),
                'assets/application',
            ),
            'modules' => $this->idsFromRepositoryOrAssets(
                fn (): array => $this->moduleRepository->findAll(),
                'assets/modules',
            ),
            'domains' => $this->idsFromRepositoryOrAssets(
                fn (): array => $this->domainRepository->findAll(),
                'assets/domains',
            ),
            'projects' => $this->idsFromRepositoryOrAssets(
                fn (): array => $this->projectRepository->findAll(),
                'assets/projects',
            ),
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }

    /**
     * @param callable(): iterable<object> $loader
     *
     * @return list<string>
     */
    private function idsFromRepositoryOrAssets(callable $loader, string $assetDirectory): array
    {
        try {
            $ids = [];

            foreach ($loader() as $entity) {
                if (!method_exists($entity, 'getId')) {
                    continue;
                }

                $id = trim((string) $entity->getId());
                if ($id !== '') {
                    $ids[] = $id;
                }
            }

            $ids = array_values(array_unique($ids));
            sort($ids, SORT_STRING);

            return $ids;
        } catch (\Throwable) {
            return $this->idsFromAssets($assetDirectory);
        }
    }

    /**
     * @return list<string>
     */
    private function idsFromAssets(string $assetDirectory): array
    {
        $directories = glob($this->projectDir.'/'.$assetDirectory.'/*', GLOB_ONLYDIR) ?: [];
        $ids = array_map('basename', $directories);
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_STRING);

        return $ids;
    }
}
