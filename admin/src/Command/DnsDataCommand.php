<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\DomainRepository;
use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:dns',
    description: 'Output project IDs and domain IDs for DNS sync.',
)]
final class DnsDataCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
        private readonly DomainRepository $domainRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projects = [];
        foreach ($this->projectRepository->findAll() as $project) {
            $id = $project->getId();
            if ($id !== null && $id !== '') {
                $projects[] = $id;
            }
        }

        $domains = [];
        foreach ($this->domainRepository->findAll() as $domain) {
            $id = $domain->getId();
            if ($id !== null && $id !== '') {
                $domains[] = $id;
            }
        }

        $projects = array_values(array_unique($projects));
        sort($projects);
        $domains = array_values(array_unique($domains));
        sort($domains);

        $output->writeln(json_encode([
            'projects' => $projects,
            'domains' => $domains,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
