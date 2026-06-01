<?php

namespace Symfonicat\Command;

use Symfonicat\Service\RuntimeConfig;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'symfonicat:data:dns',
    description: 'Output subdomain affixes and domain IDs for DNS sync.',
)]
final class DnsDataCommand extends Command
{
    public function __construct(
        private readonly RuntimeConfig $runtimeConfig,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $subdomains = [];
        foreach ($this->runtimeConfig->subdomains() as $subdomain) {
            $affix = trim((string) $subdomain->getAffix());
            $domainId = trim((string) $subdomain->getDomain()?->getId());

            if ($affix === '') {
                continue;
            }

            $subdomains[] = [
                'affix' => $affix,
                'domain' => $domainId === '' ? null : $domainId,
                'host' => $domainId === '' ? $affix : $affix.'.'.$domainId,
            ];
        }

        $domains = [];
        foreach ($this->runtimeConfig->domains() as $domain) {
            $id = $domain->getId(false);
            if ($id !== null && $id !== '') {
                $domains[] = $id;
            }
        }

        usort($subdomains, static fn (array $left, array $right): int => ($left['host'] ?? '') <=> ($right['host'] ?? ''));
        $domains = array_values(array_unique($domains));
        sort($domains);

        $output->writeln(json_encode([
            'subdomains' => $subdomains,
            'domains' => $domains,
        ], JSON_THROW_ON_ERROR));

        return Command::SUCCESS;
    }
}
