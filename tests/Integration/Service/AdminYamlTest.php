<?php

namespace App\Tests\Integration\Service;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Service\AdminYaml;
use Symfony\Component\Yaml\Yaml;

final class AdminYamlTest extends SymfonicatKernelTestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectDir = sys_get_temp_dir().'/symfonicat_admin_yaml_'.bin2hex(random_bytes(6));
        mkdir($this->projectDir.'/config/packages', 0755, true);
        file_put_contents($this->projectDir.'/config/packages/symfonicat.yaml', <<<'YAML'
symfonicat:
    vendors:
        - symfonicat
        - custom
YAML);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);

        parent::tearDown();
    }

    public function testDumpPreservesVendorsAndLoadRestoresRows(): void
    {
        $connection = $this->entityManager()->getConnection();
        $connection->insert('symfonicat_admin', [
            'id' => 7,
            'email' => 'admin@example.com',
            'roles' => json_encode(['ROLE_EDITOR'], JSON_THROW_ON_ERROR),
            'password' => 'hashed-password',
            'mfa_secret' => 'totp-secret',
        ]);
        $connection->insert('symfonicat_domain', [
            'id' => 'core/example.com',
            'vendor' => 'core',
        ]);
        $connection->insert('symfonicat_project', [
            'id' => 'core/project1',
            'vendor' => 'core',
        ]);
        $connection->insert('symfonicat_domain_project', [
            'domain_id' => 'core/example.com',
            'project_id' => 'core/project1',
        ]);

        $adminYaml = new AdminYaml($connection, $this->projectDir);
        $dumpCounts = $adminYaml->dump();

        self::assertArrayNotHasKey('symfonicat_admin', $dumpCounts);
        self::assertSame(1, $dumpCounts['symfonicat_domain_project']);

        $config = Yaml::parseFile($this->projectDir.'/config/packages/symfonicat.yaml');
        self::assertSame(['symfonicat', 'custom'], $config['symfonicat']['vendors']);
        self::assertArrayNotHasKey('symfonicat_admin', $config['symfonicat']['admin']);

        $connection->executeStatement('DELETE FROM symfonicat_domain_project');
        $connection->executeStatement('DELETE FROM symfonicat_admin');
        $connection->executeStatement('DELETE FROM symfonicat_project');
        $connection->executeStatement('DELETE FROM symfonicat_domain');

        $loadCounts = $adminYaml->load();

        self::assertArrayNotHasKey('symfonicat_admin', $loadCounts);
        self::assertSame(1, $loadCounts['symfonicat_domain_project']);
        self::assertFalse($connection->fetchOne('SELECT email FROM symfonicat_admin WHERE id = 7'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM symfonicat_domain_project'));
    }

    public function testCheckedInElectronFaviconSeedUsesDefaultSvg(): void
    {
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $config = Yaml::parseFile($projectDir.'/config/packages/symfonicat.yaml');
        $electronRows = $config['symfonicat']['admin']['symfonicat_electron'];

        self::assertSame('electron/favicon/domain/example.com.svg', $electronRows[0]['favicon']);
        self::assertFileExists($projectDir.'/public/electron/favicon/domain/example.com.svg');
        self::assertSame(
            file_get_contents($projectDir.'/public/default/favicon.svg'),
            file_get_contents($projectDir.'/public/electron/favicon/domain/example.com.svg'),
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
