<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Electron;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

final class RuntimeAssetBaseTest extends SymfonicatWebTestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryFiles) as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }

        $this->temporaryDirectories = [];
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function testDomainShellUsesDomainAssetBase(): void
    {
        $this->createDomain('example.com');

        $this->setHost('example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/domains/example.com/favicon.svg"', $content);
    }

    public function testProjectShellUsesProjectAssetBase(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain1', $domain);

        $this->setHost('subdomain1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/subdomains/subdomain1/favicon.svg"', $content);
    }

    public function testProjectShellFallsBackToDomainAssetBaseWhenProjectFolderIsMissing(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain_without_assets', $domain);

        $this->setHost('subdomain_without_assets.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/domains/example.com/favicon.svg"', $content);
    }

    public function testProjectShellFallsBackToDefaultAssetBaseWhenProjectAndDomainFoldersAreMissing(): void
    {
        $domain = $this->createDomain('missing-assets.example');
        $this->createProject('subdomain_without_any_assets', $domain);

        $this->setHost('subdomain_without_any_assets.missing-assets.example');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/default/favicon.svg"', $content);
    }

    public function testDomainShellFallsBackToDefaultAssetBaseWhenDomainFolderIsMissing(): void
    {
        $this->createDomain('missing-assets.example');

        $this->setHost('missing-assets.example');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/default/favicon.svg"', $content);
    }

    public function testExplicitApplicationContextUsesFullIdAssetBase(): void
    {
        $application = $this->createApplication('core/test');

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame(
            '/core/test/favicon.svg',
            trim($twig->createTemplate('{{ symfonicat_asset("favicon.svg", application) }}')->render([
                'application' => $application,
            ])),
        );
    }

    public function testExplicitProjectContextUsesProjectAssetBase(): void
    {
        $subdomain = $this->createProject('subdomain1');

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame(
            '/subdomains/subdomain1/favicon.svg',
            trim($twig->createTemplate('{{ symfonicat_asset("favicon.svg", subdomain) }}')->render([
                'subdomain' => $subdomain,
            ])),
        );
    }

    public function testExplicitDomainContextUsesDomainAssetBase(): void
    {
        $domain = $this->createDomain('example.com');

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame(
            '/domains/example.com/favicon.svg',
            trim($twig->createTemplate('{{ symfonicat_asset("favicon.svg", domain) }}')->render([
                'domain' => $domain,
            ])),
        );
    }

    public function testExplicitElectronContextUsesElectronAssetBase(): void
    {
        $electron = (new Electron())->setId('example-test');

        /** @var Environment $twig */
        $twig = self::getTestContainer()->get(Environment::class);

        self::assertSame(
            '/electron/example-test/favicon.svg',
            trim($twig->createTemplate('{{ symfonicat_asset("favicon.svg", electron) }}')->render([
                'electron' => $electron,
            ])),
        );
    }

    public function testProjectShellUsesProjectAssetBaseWhenProjectFolderExists(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain_with_assets', $domain);
        $this->createTemporaryPublicDirectory('subdomains/subdomain_with_assets');
        $this->createTemporaryPublicFile('subdomains/subdomain_with_assets/favicon.svg', 'subdomain-favicon');

        $this->setHost('subdomain_with_assets.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/subdomains/subdomain_with_assets/favicon.svg"', $content);
    }

    public function testProjectShellFallsBackToDomainAssetWhenProjectFileIsMissing(): void
    {
        $domain = $this->createDomain('fallback.example');
        $this->createProject('subdomain_missing_file', $domain);
        $this->createTemporaryPublicDirectory('domains/fallback.example');
        $this->createTemporaryPublicFile('domains/fallback.example/favicon.svg', 'domain-favicon');
        $this->createTemporaryPublicDirectory('subdomains/subdomain_missing_file');

        $this->setHost('subdomain_missing_file.fallback.example');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringContainsString('<link rel="icon" href="/domains/fallback.example/favicon.svg"', $content);
        self::assertStringNotContainsString('/subdomains/subdomain_missing_file/favicon.svg', $content);
    }

    public function testMissingDefaultAssetThrows(): void
    {
        $requestStack = self::getContainer()->get(RequestStack::class);
        $request = Request::create('/', 'GET', server: ['HTTP_HOST' => 'missing-assets.example']);
        $requestStack->push($request);

        try {
            /** @var Environment $twig */
            $twig = self::getTestContainer()->get(Environment::class);

            $this->expectException(\Twig\Error\RuntimeError::class);
            $this->expectExceptionMessage('Asset "missing.svg" was not found in the subdomain, domain, or default public folders.');
            $twig->createTemplate('{{ symfonicat_asset("missing.svg") }}')->render();
        } finally {
            $requestStack->pop();
        }
    }

    private function createTemporaryPublicDirectory(string $path): void
    {
        $directory = self::getContainer()->getParameter('kernel.project_dir').'/public/'.trim($path, '/');

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            self::fail(sprintf('Could not create temporary public directory "%s".', $directory));
        }

        $this->temporaryDirectories[] = $directory;
    }

    private function createTemporaryPublicFile(string $path, string $contents): void
    {
        $file = self::getContainer()->getParameter('kernel.project_dir').'/public/'.trim($path, '/');
        $directory = dirname($file);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            self::fail(sprintf('Could not create temporary public file directory "%s".', $directory));
        }

        if (file_put_contents($file, $contents) === false) {
            self::fail(sprintf('Could not create temporary public file "%s".', $file));
        }

        $this->temporaryFiles[] = $file;
    }
}
