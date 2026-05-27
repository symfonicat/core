<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Service\PackageDiscoveryService;
use Symfonicat\Service\ScriptlingService;

final class ScriptlingServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/symfonicat_scriptling_'.bin2hex(random_bytes(6));

        mkdir($this->projectDir.'/vendor/composer', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/allowed/extensions/allowed-extension', 0755, true);
        mkdir($this->projectDir.'/extensions/test', 0755, true);

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

        file_put_contents($this->projectDir.'/vendor/composer/installed.json', json_encode([
            'packages' => [
                [
                    'name' => 'symfonicat/allowed',
                    'install-path' => '../symfonicat/allowed',
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        file_put_contents($this->projectDir.'/extensions/test/go.mod', <<<'GO'
module example.com/symfonicat/extensions/test

go 1.22
GO);

        file_put_contents($this->projectDir.'/vendor/symfonicat/allowed/extensions/allowed-extension/go.mod', <<<'GO'
module example.com/symfonicat/extensions/allowed-extension

go 1.22
GO);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);

        parent::tearDown();
    }

    public function testCopyScriptLinesSkipRootExtensionsAndCopyVendorExtensions(): void
    {
        $service = $this->createService();
        $lines = $service->copyScriptLines();

        self::assertSame('set -eu', $lines[0]);
        self::assertSame(
            'mkdir -p /symfonicat/extensions/symfonicat/allowed',
            $lines[1],
        );
        self::assertStringContainsString(
            'cp -R '.$this->projectDir.'/vendor/symfonicat/allowed/extensions/allowed-extension',
            implode("\n", $lines),
        );
        self::assertStringNotContainsString('/symfonicat/extensions/test', implode("\n", $lines));
    }

    public function testXcaddyFlagsIncludeRootAndVendorExtensions(): void
    {
        $service = $this->createService();
        $flags = $service->xcaddyFlags();

        self::assertSame([
            '--with example.com/symfonicat/extensions/allowed-extension='.$this->projectDir.'/vendor/symfonicat/allowed/extensions/allowed-extension',
            '--with example.com/symfonicat/extensions/test='.$this->projectDir.'/extensions/test',
        ], $flags);
    }

    private function createService(): ScriptlingService
    {
        $packageDiscoveryService = new PackageDiscoveryService($this->projectDir);

        return new ScriptlingService($packageDiscoveryService, $this->projectDir);
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
