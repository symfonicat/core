<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[AsCommand(
    name: 'symfonicat:test:mercure',
    description: 'Publish a Turbo Stream update to verify Mercure connectivity.',
)]
final class MercureTestCommand extends Command
{
    public function __construct(
        private readonly HubInterface $hub,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'topic',
            null,
            InputOption::VALUE_REQUIRED,
            'Mercure topic to publish to',
            'mercure-debug'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $topic = (string) $input->getOption('topic');
        if ($topic === '') {
            $output->writeln('Topic must not be empty.');
            return Command::FAILURE;
        }

        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('c');
        $message = sprintf('Mercure test at %s', $timestamp);
        $payload = sprintf(
            '<turbo-stream action="append" target="mercure-debug"><template><div data-mercure-debug="%s">%s</div></template></turbo-stream>',
            htmlspecialchars($timestamp, ENT_QUOTES),
            htmlspecialchars($message, ENT_QUOTES)
        );

        try {
            $id = $this->hub->publish(new Update($topic, $payload));
        } catch (\Throwable $exception) {
            $output->writeln(sprintf('Publish failed: %s', $exception->getMessage()));
            return Command::FAILURE;
        }

        $output->writeln(json_encode([
            'status' => 'published',
            'topic' => $topic,
            'id' => $id,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
