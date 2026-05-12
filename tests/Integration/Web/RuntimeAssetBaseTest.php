<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

final class RuntimeAssetBaseTest extends SymfonicatWebTestCase
{
    /** @var list<string> */
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            if (is_dir($directory)) {
                @rmdir($directory);
            }
        }

        $this->temporaryDirectories = [];

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
        $this->createProject('project1', $domain);

        $this->setHost('project1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/projects/project1/favicon.svg"', $content);
    }

    public function testProjectShellFallsBackToDomainAssetBaseWhenProjectFolderIsMissing(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('project_without_assets', $domain);

        $this->setHost('project_without_assets.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/domains/example.com/favicon.svg"', $content);
    }

    public function testProjectShellFallsBackToDefaultAssetBaseWhenProjectAndDomainFoldersAreMissing(): void
    {
        $domain = $this->createDomain('missing-assets.example');
        $this->createProject('project_without_any_assets', $domain);

        $this->setHost('project_without_any_assets.missing-assets.example');
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

    public function testProjectShellUsesProjectAssetBaseWhenProjectFolderExists(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('project_with_assets', $domain);
        $this->createTemporaryPublicDirectory('projects/project_with_assets');

        $this->setHost('project_with_assets.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client()->getResponse()->getContent();

        self::assertStringNotContainsString('<base ', $content);
        self::assertStringContainsString('<link rel="icon" href="/projects/project_with_assets/favicon.svg"', $content);
    }

    private function createTemporaryPublicDirectory(string $path): void
    {
        $directory = self::getContainer()->getParameter('kernel.project_dir').'/public/'.trim($path, '/');

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            self::fail(sprintf('Could not create temporary public directory "%s".', $directory));
        }

        $this->temporaryDirectories[] = $directory;
    }
}
