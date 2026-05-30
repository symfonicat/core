<?php

namespace App\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfonicat\Command\ExtCoreCommand;
use Symfonicat\Command\ExtListCommand;
use Symfonicat\Command\ExtPathsCommand;
use Symfonicat\Service\PackageDiscoveryService;

final class ExtListCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/symfonicat_ext_list_'.bin2hex(random_bytes(6));

        mkdir($this->projectDir.'/vendor/composer', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/allowed/ext/vendor-alpha', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/core-no-ext', 0755, true);
        mkdir($this->projectDir.'/ext/root-alpha', 0755, true);
        mkdir($this->projectDir.'/ext/root-beta', 0755, true);

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

        file_put_contents($this->projectDir.'/vendor/symfonicat/core-no-ext/composer.json', json_encode([
            'name' => 'symfonicat/core-no-ext',
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
                [
                    'name' => 'symfonicat/core-no-ext',
                    'install-path' => '../symfonicat/core-no-ext',
                ],
            ],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);

        parent::tearDown();
    }

    public function testListsFlattenedExtensionNamesAcrossSymfonicatPackages(): void
    {
        $command = new ExtListCommand(
            new PackageDiscoveryService($this->projectDir),
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        self::assertSame(
            'vendor-alpha root-alpha root-beta',
            trim($tester->getDisplay()),
        );
    }

    public function testListsFlattenedExtensionPathsAcrossSymfonicatPackages(): void
    {
        $command = new ExtPathsCommand(
            new PackageDiscoveryService($this->projectDir),
            $this->projectDir,
        );

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        self::assertSame(
            'vendor/symfonicat/allowed/ext/vendor-alpha ext/root-alpha ext/root-beta',
            trim($tester->getDisplay()),
        );
    }

    public function testListsNativeExtensionDirectoriesFromNativeExt(): void
    {
        mkdir($this->projectDir.'/native/ext/native-alpha', 0755, true);
        mkdir($this->projectDir.'/native/ext/native-beta', 0755, true);

        $command = new ExtCoreCommand($this->projectDir);

        $tester = new CommandTester($command);
        $tester->execute([], ['interactive' => false]);

        self::assertSame(
            'native-alpha native-beta',
            trim($tester->getDisplay()),
        );
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
