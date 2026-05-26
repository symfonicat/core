<?php

namespace App\Tests\Integration\Command;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
use Symfony\Bundle\FrameworkBundle\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

final class ApplicationBuildCommandTest extends SymfonicatKernelTestCase
{
    public function testGeneratesApplicationSkeletonsIntoApplicationDirectory(): void
    {
        $domain = $this->createDomain('example.com');
        $subdomain = $this->createSubdomain('subdomain1', $domain);
        $endpoint = $this->createEndpoint('core/launch')
            ->setArguments(['launch']);
        $this->entityManager()->flush();

        $fallbackApplication = (new Application())
            ->setId('core/fallback')
            ->setName('Fallback App')
            ->setDomain($domain);

        $overrideApplication = (new Application())
            ->setId('core/override')
            ->setName('Override App')
            ->setDomain($domain)
            ->setSubdomain($subdomain);

        $endpointApplication = (new Application())
            ->setId('core/endpoint')
            ->setName('Endpoint App')
            ->setDomain($domain)
            ->setEndpoint($endpoint);

        $this->entityManager()->persist($fallbackApplication);
        $this->entityManager()->persist($overrideApplication);
        $this->entityManager()->persist($endpointApplication);
        $this->entityManager()->flush();

        $overrideTemplate = $this->projectDir().'/templates/application/overrides/core/override.js.twig';
        $this->ensureParentDirectory($overrideTemplate);
        file_put_contents($overrideTemplate, <<<'TWIG'
module.exports = {{ application_config.name|json_encode|raw }};
TWIG);

        try {
            $tester = $this->runCommand();

            self::assertSame(0, $tester->getStatusCode());

            $fallbackDir = $this->projectDir().'/application/core/fallback';
            $overrideDir = $this->projectDir().'/application/core/override';
            $endpointDir = $this->projectDir().'/application/core/endpoint';

            self::assertFileExists($fallbackDir.'/main.js');
            self::assertFileExists($fallbackDir.'/package.json');
            self::assertFileExists($fallbackDir.'/README.md');

            self::assertFileExists($overrideDir.'/main.js');
            self::assertFileExists($overrideDir.'/package.json');
            self::assertFileExists($overrideDir.'/README.md');

            self::assertFileExists($endpointDir.'/main.js');
            self::assertFileExists($endpointDir.'/package.json');
            self::assertFileExists($endpointDir.'/README.md');

            self::assertStringContainsString('core%2Ffallback', file_get_contents($fallbackDir.'/main.js'));
            self::assertStringContainsString('module.exports = "Override App";', file_get_contents($overrideDir.'/main.js'));
            self::assertStringContainsString('core%2Fendpoint', file_get_contents($endpointDir.'/main.js'));

            $fallbackPackage = json_decode(file_get_contents($fallbackDir.'/package.json') ?: '', true, flags: JSON_THROW_ON_ERROR);
            self::assertSame('core-fallback', $fallbackPackage['name']);
            self::assertSame('electron-builder', $fallbackPackage['scripts']['build']);
            self::assertSame('dist', $fallbackPackage['build']['directories']['output']);
            self::assertSame('symfonicat.core.fallback', $fallbackPackage['build']['appId']);
            self::assertArrayHasKey('electron', $fallbackPackage['dependencies']);
            self::assertArrayHasKey('electron-builder', $fallbackPackage['devDependencies']);
            self::assertStringContainsString('npm run build', file_get_contents($fallbackDir.'/README.md'));

            self::assertFileDoesNotExist($fallbackDir.'/resources');
            self::assertFileDoesNotExist($fallbackDir.'/chrome_100_percent.pak');
        } finally {
            $this->removePath($this->projectDir().'/application/core');
            $this->removePath($this->projectDir().'/templates/application/overrides/core');
        }
    }

    private function runCommand(): CommandTester
    {
        $application = new ConsoleApplication(self::$kernel);
        $application->setAutoExit(false);

        $command = $application->find('symfonicat:application:build');
        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        return $tester;
    }

    private function projectDir(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir');
    }

    private function ensureParentDirectory(string $path): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create directory "%s".', $directory));
        }
    }

    private function removePath(string $path): void
    {
        if (is_file($path) || is_link($path)) {
            @unlink($path);

            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = array_values(array_diff(scandir($path) ?: [], ['.', '..']));
        foreach ($entries as $entry) {
            $this->removePath($path.'/'.$entry);
        }

        @rmdir($path);
    }
}
