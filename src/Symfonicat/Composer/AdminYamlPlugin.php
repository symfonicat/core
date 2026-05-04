<?php

namespace Symfonicat\Composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

final class AdminYamlPlugin implements PluginInterface, EventSubscriberInterface
{
    public function activate(Composer $composer, IOInterface $io): void
    {
    }

    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'runLoad',
        ];
    }

    public function runLoad(Event $event): void
    {
        self::postInstall($event);
    }

    public static function postInstall(Event $event): void
    {
        $projectDir = getcwd() ?: dirname(__DIR__, 3);
        $console = $projectDir.'/bin/console';

        if (!is_file($console)) {
            $event->getIO()->writeError('<warning>Skipping symfonicat:load because bin/console was not found.</warning>');

            return;
        }

        $command = [
            PHP_BINARY,
            $console,
            'symfonicat:load',
            '--no-interaction',
        ];

        $descriptorSpec = [
            0 => ['file', defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptorSpec, $pipes, $projectDir);
        if (!is_resource($process)) {
            throw new \RuntimeException('Unable to run symfonicat:load after Composer install.');
        }

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if (is_string($stdout) && trim($stdout) !== '') {
            $event->getIO()->write(trim($stdout));
        }

        if (is_string($stderr) && trim($stderr) !== '') {
            $event->getIO()->writeError(trim($stderr));
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException(sprintf('symfonicat:load failed with exit code %d.', $exitCode));
        }
    }
}
