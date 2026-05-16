<?php

namespace App\Tests\Integration\Service;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Service\AdminYaml;
use Symfony\Component\Yaml\Yaml;

final class AdminYamlTest extends SymfonicatKernelTestCase
{
    private string $subdomainDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subdomainDir = sys_get_temp_dir().'/symfonicat_admin_yaml_'.bin2hex(random_bytes(6));
        mkdir($this->subdomainDir.'/config/packages', 0755, true);
        file_put_contents($this->subdomainDir.'/config/packages/symfonicat.yaml', <<<'YAML'
symfonicat:
    vendors:
        - symfonicat
        - custom
YAML);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->subdomainDir);

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
            'id' => 'example.com',
        ]);
        $connection->insert('symfonicat_subdomain', [
            'id' => 'core/subdomain1',
            'vendor' => 'core',
        ]);
        $connection->insert('symfonicat_domain_subdomain', [
            'domain_id' => 'example.com',
            'subdomain_id' => 'core/subdomain1',
        ]);

        $adminYaml = new AdminYaml($connection, $this->subdomainDir);
        $dumpCounts = $adminYaml->dump();

        self::assertArrayNotHasKey('symfonicat_admin', $dumpCounts);
        self::assertSame(1, $dumpCounts['symfonicat_domain_subdomain']);

        $config = Yaml::parseFile($this->subdomainDir.'/config/packages/symfonicat.yaml');
        self::assertSame(['symfonicat', 'custom'], $config['symfonicat']['vendors']);
        self::assertArrayNotHasKey('symfonicat_admin', $config['symfonicat']['admin']);

        $connection->executeStatement('DELETE FROM symfonicat_domain_subdomain');
        $connection->executeStatement('DELETE FROM symfonicat_admin');
        $connection->executeStatement('DELETE FROM symfonicat_subdomain');
        $connection->executeStatement('DELETE FROM symfonicat_domain');

        $loadCounts = $adminYaml->load();

        self::assertArrayNotHasKey('symfonicat_admin', $loadCounts);
        self::assertSame(1, $loadCounts['symfonicat_domain_subdomain']);
        self::assertFalse($connection->fetchOne('SELECT email FROM symfonicat_admin WHERE id = 7'));
        self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM symfonicat_domain_subdomain'));
    }

    public function testCheckedInElectronSeedHasPlainIdAndNoVendorOrFavicon(): void
    {
        $subdomainDir = self::getContainer()->getParameter('kernel.project_dir');
        $config = Yaml::parseFile($subdomainDir.'/config/packages/symfonicat.yaml');
        $electronRows = $config['symfonicat']['admin']['symfonicat_electron'];

        self::assertSame('example-test', $electronRows[0]['id']);
        self::assertSame('example.com', $electronRows[0]['domain_id']);
        self::assertArrayNotHasKey('vendor', $electronRows[0]);
        self::assertArrayNotHasKey('favicon', $electronRows[0]);
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
