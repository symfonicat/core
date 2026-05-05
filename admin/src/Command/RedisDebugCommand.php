<?php

namespace Symfonicat\Command;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'symfonicat:test:redis',
    description: 'Validate Redis connectivity and basic operations.',
)]
final class RedisDebugCommand extends Command
{
    public function __construct(
        private readonly string $redisUrl,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $targets = [
            'REDIS_URL' => $this->redisUrl,
            'CACHE (REDIS_URL)' => $this->redisUrl,
            'SESSION (REDIS_URL)' => $this->redisUrl,
        ];

        foreach ($targets as $label => $dsn) {
            if ($dsn === '') {
                $io->warning(sprintf('%s is empty; skipping.', $label));
                continue;
            }

            $io->section(sprintf('%s', $label));

            try {
                $redis = RedisAdapter::createConnection($dsn);
                $pong = $redis->ping();
                $io->text(sprintf('PING: %s', $pong));

                $key = sprintf('symfonicat:test:redis:%s', bin2hex(random_bytes(4)));
                $value = bin2hex(random_bytes(6));
                $redis->setex($key, 10, $value);
                $fetched = $redis->get($key);

                if ($fetched !== $value) {
                    $io->error('SET/GET mismatch.');
                } else {
                    $io->success('SET/GET OK.');
                }

                $info = $redis->info();
                if (is_array($info)) {
                    $io->text(sprintf(
                        'Server: %s | Redis: %s | DB: %s',
                        $info['redis_version'] ?? 'n/a',
                        $info['redis_version'] ?? 'n/a',
                        $info['db0']['keys'] ?? 'n/a'
                    ));
                }
            } catch (\Throwable $exception) {
                $io->error(sprintf('%s failed: %s', $label, $exception->getMessage()));
            }
        }

        return Command::SUCCESS;
    }
}
