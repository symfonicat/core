<?php

namespace App\Tests\Unit\Command;

use PHPUnit\Framework\TestCase;
use Symfonicat\Command\DiscoverExtNamesCommand;
use Symfonicat\Command\DiscoverExtPathsCommand;
use Symfonicat\Command\DiscoverGoNamesCommand;
use Symfonicat\Command\DiscoverGoPathsCommand;
use Symfonicat\Service\NativeDiscoveryService;
use Symfonicat\Service\PackageDiscoveryService;
use Symfony\Component\Console\Tester\CommandTester;

final class NativeDiscoveryCommandsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/symfonicat_native_discovery_'.bin2hex(random_bytes(6));

        mkdir($this->projectDir.'/vendor/composer', 0755, true);
        mkdir($this->projectDir.'/native/go/root-go', 0755, true);
        mkdir($this->projectDir.'/native/ext/root-ext', 0755, true);
        mkdir($this->projectDir.'/core/native/go/core-go', 0755, true);
        mkdir($this->projectDir.'/core/native/ext/core-ext', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/allowed/native/go/vendor-go', 0755, true);
        mkdir($this->projectDir.'/vendor/symfonicat/allowed/native/ext/vendor-ext', 0755, true);

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
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);

        parent::tearDown();
    }

    public function testDiscoverGoPathsIncludesRootCoreAndVendorPackages(): void
    {
        $tester = new CommandTester(new DiscoverGoPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => './'], ['interactive' => false]);

        self::assertSame('native/go/root-go', trim($tester->getDisplay()));

        $tester = new CommandTester(new DiscoverGoPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => 'core'], ['interactive' => false]);

        self::assertSame('core/native/go/core-go', trim($tester->getDisplay()));

        $tester = new CommandTester(new DiscoverGoPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => 'vendor/**/**/'], ['interactive' => false]);

        self::assertSame('vendor/symfonicat/allowed/native/go/vendor-go', trim($tester->getDisplay()));
    }

    public function testDiscoverGoNamesReturnsBaseFolderNames(): void
    {
        $tester = new CommandTester(new DiscoverGoNamesCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => './'], ['interactive' => false]);

        self::assertSame('root-go', trim($tester->getDisplay()));
    }

    public function testDiscoverExtPathsIncludesRootCoreAndVendorPackages(): void
    {
        $tester = new CommandTester(new DiscoverExtPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => './'], ['interactive' => false]);

        self::assertSame('native/ext/root-ext', trim($tester->getDisplay()));

        $tester = new CommandTester(new DiscoverExtPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => 'core'], ['interactive' => false]);

        self::assertSame('core/native/ext/core-ext', trim($tester->getDisplay()));

        $tester = new CommandTester(new DiscoverExtPathsCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => 'vendor/**/**/'], ['interactive' => false]);

        self::assertSame('vendor/symfonicat/allowed/native/ext/vendor-ext', trim($tester->getDisplay()));
    }

    public function testDiscoverExtNamesReturnsBaseFolderNames(): void
    {
        $tester = new CommandTester(new DiscoverExtNamesCommand($this->createDiscoveryService()));
        $tester->execute(['pattern' => 'vendor/**/**/'], ['interactive' => false]);

        self::assertSame('vendor-ext', trim($tester->getDisplay()));
    }

    private function createDiscoveryService(): NativeDiscoveryService
    {
        return new NativeDiscoveryService(
            new PackageDiscoveryService($this->projectDir),
            $this->projectDir,
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
