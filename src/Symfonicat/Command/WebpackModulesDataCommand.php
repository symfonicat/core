<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ModuleRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:webpack',
    description: 'Output domain and project module data for webpack.',
)]
final class WebpackModulesDataCommand extends Command
{
    public function __construct(
        private readonly DomainRepository $domainRepository,
        private readonly ModuleRepository $moduleRepository,
        private readonly ProjectRepository $projectRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $modules = [];
        foreach ($this->moduleRepository->findAll() as $module) {
            $id = $module->getId();
            if ($id !== null && $id !== '') {
                $modules[] = $id;
            }
        }
        $modules = array_values(array_unique($modules));

        $domains = [];
        foreach ($this->domainRepository->findAll() as $domain) {
            $id = $domain->getId();
            if ($id !== null && $id !== '') {
                $domains[] = $id;
            }
        }
        $domains = array_values(array_unique($domains));

        $projects = [];
        foreach ($this->projectRepository->findAll() as $project) {
            $id = $project->getId();
            if ($id !== null && $id !== '') {
                $projects[] = $id;
            }
        }
        $projects = array_values(array_unique($projects));

        $output->writeln(json_encode([
            'modules' => $modules,
            'domains' => $domains,
            'projects' => $projects,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
