<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'symfonicat:public-suffix:refresh',
    description: 'Download the latest public suffix list into public_suffix_list.dat.',
)]
final class PublicSuffixRefreshCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $subdomainDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $response = $this->httpClient->request('GET', 'https://publicsuffix.org/list/public_suffix_list.dat');
            $content = $response->getContent();
        } catch (\Throwable $exception) {
            $io->error(sprintf('Download failed: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $target = rtrim($this->subdomainDir, '/').'/public_suffix_list.dat';
        file_put_contents($target, $content);

        $io->success(sprintf('Updated %s', $target));

        return Command::SUCCESS;
    }
}
