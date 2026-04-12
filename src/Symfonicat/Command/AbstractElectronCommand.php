<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

abstract class AbstractElectronCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('project', null, InputOption::VALUE_REQUIRED, 'Limit the Electron operation to one project slug.');
    }

    /**
     * @return list<string>
     */
    abstract protected function electronArguments(InputInterface $input): array;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $command = ['node', 'electron.js', ...$this->electronArguments($input)];
        $project = trim((string) $input->getOption('project'));

        if ($project !== '') {
            $command[] = sprintf('--project=%s', $project);
        }

        $process = new Process($command, $this->projectDir);
        $process->setTimeout(null);
        $process->run(static function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });

        return $process->isSuccessful() ? Command::SUCCESS : Command::FAILURE;
    }
}
