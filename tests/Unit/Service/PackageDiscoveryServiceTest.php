<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Service\PackageDiscoveryService;

final class PackageDiscoveryServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/symfonicat_package_discovery_'.bin2hex(random_bytes(6));

        mkdir($this->projectDir.'/vendor/composer', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/allowed/assets/module/allowed-module', 0755, true);
        mkdir($this->projectDir.'/vendor/acme/ignored/assets/module/ignored-module', 0755, true);
        mkdir($this->projectDir.'/assets/module/root-module', 0755, true);

        file_put_contents($this->projectDir.'/composer.json', json_encode([
            'name' => 'symfonicat/core',
            'extra' => [
                'symfonicat' => true,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->projectDir.'/vendor/symfonicat/allowed/composer.json', json_encode([
            'name' => 'symfonicat/allowed',
            'extra' => [
                'symfonicat' => true,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->projectDir.'/vendor/acme/ignored/composer.json', json_encode([
            'name' => 'acme/ignored',
            'extra' => [
                'symfonicat' => false,
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->projectDir.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                [
                    'name' => 'symfonicat/allowed',
                    'install-path' => '../symfonicat/allowed',
                ],
                [
                    'name' => 'acme/ignored',
                    'install-path' => '../acme/ignored',
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);

        parent::tearDown();
    }

    public function testFindSymfonicatPackagesUsesComposerExtraFlag(): void
    {
        $service = new PackageDiscoveryService($this->projectDir);
        $packages = $service->findSymfonicatPackages();

        self::assertSame(['symfonicat/allowed', 'symfonicat/core'], array_column($packages, 'name'));
        self::assertSame($this->projectDir.'/vendor/symfonicat/allowed', $packages[0]['installPath']);
        self::assertSame($this->projectDir, $packages[1]['installPath']);
    }

    public function testDiscoverEntryDirectoriesSkipsPackagesWithoutComposerOptIn(): void
    {
        $service = new PackageDiscoveryService($this->projectDir);
        $entries = $service->discoverEntryDirectories('module');

        self::assertArrayHasKey('symfonicat/core/root-module', $entries);
        self::assertArrayHasKey('symfonicat/allowed/allowed-module', $entries);
        self::assertArrayNotHasKey('acme/ignored/ignored-module', $entries);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}
