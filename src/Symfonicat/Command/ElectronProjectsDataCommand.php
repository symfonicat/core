<?php

namespace Symfonicat\Command;

use Symfonicat\Repository\ProjectRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:electron',
    description: 'Output project data for Electron packaging.',
    aliases: ['electron:data:projects'],
)]
final class ElectronProjectsDataCommand extends Command
{
    public function __construct(
        private readonly ProjectRepository $projectRepository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $projects = [];

        foreach ($this->projectRepository->findAll() as $project) {
            $slug = $project->getSlug();
            if ($slug === null || $slug === '') {
                continue;
            }

            $name = $project->getName();
            $icon = $project->getIcon();
            $domainIds = [];
            foreach ($project->getDomains() as $domain) {
                $domainId = $domain->getId();
                if ($domainId === null || $domainId === '') {
                    continue;
                }

                $domainIds[] = $domainId;
            }
            $domainIds = array_values(array_unique($domainIds));
            sort($domainIds);
            $primaryDomain = $domainIds[0] ?? null;

            $projects[] = [
                'slug' => $slug,
                'name' => is_string($name) && $name !== '' ? $name : $slug,
                'icon' => is_string($icon) && $icon !== '' ? $icon : null,
                'domain' => is_string($primaryDomain) && $primaryDomain !== '' ? $primaryDomain : null,
                'domains' => $domainIds,
            ];
        }

        usort($projects, static fn (array $left, array $right): int => strcmp((string) $left['slug'], (string) $right['slug']));

        $output->writeln(json_encode([
            'projects' => $projects,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
