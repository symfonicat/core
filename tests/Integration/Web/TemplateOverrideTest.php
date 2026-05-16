<?php

namespace App\Tests\Integration\Web;

use App\Tests\Support\SymfonicatWebTestCase;

/**
 * MainController::resolveTemplate() lets operators ship a per-domain or
 * per-subdomain Twig template that transparently replaces the default
 * domain/main.html.twig or subdomain/main.html.twig. These tests drop an
 * override template into the live filesystem for the duration of the test and
 * assert the resolver picks it up.
 *
 * We write to the real templates/ tree because Twig's cache + filesystem
 * loader is what actually resolves the override; stubbing it would skip the
 * exact code path we want to verify. Files are cleaned up in tearDown() and
 * their namespaces ("example.com", "core/subdomain1") are unlikely to collide with
 * real overrides in the core repository.
 */
final class TemplateOverrideTest extends SymfonicatWebTestCase
{
    /** @var list<string> */
    private array $writtenTemplates = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenTemplates as $path) {
            if (is_file($path)) {
                @unlink($path);
            }

            $directory = dirname($path);
            while (is_dir($directory) && basename($directory) !== 'templates') {
                if (@rmdir($directory)) {
                    $directory = dirname($directory);

                    continue;
                }

                break;
            }
        }

        $this->writtenTemplates = [];

        // Twig's cache remembers the override if we created it mid-request.
        // Blow the test-env cache away so the next test gets a clean loader.
        $this->clearTwigCache();

        parent::tearDown();
    }

    public function testDomainOverrideTemplateReplacesDefaultShell(): void
    {
        $this->createDomain('example.com');

        $this->writeTemplate(
            'domain/overrides/example.com.html.twig',
            <<<'TWIG'
            {% extends 'base.html.twig' %}
            {% block body %}
                <h1 data-testid="domain-override">custom example.com landing</h1>
            {% endblock %}
            TWIG,
        );

        $this->clearTwigCache();
        $this->setHost('example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '[data-testid="domain-override"]',
            'custom example.com landing',
            'domain/overrides/<domain-id>.html.twig must win over domain/main.html.twig',
        );
    }

    public function testDomainFallsBackToMainWhenOverrideMissing(): void
    {
        // No override file: the resolver catches LoaderError and returns the
        // default template. This guards against a regression where a typo'd
        // override filename would 500 the whole domain instead of silently
        // using the default.
        $this->createDomain('example.com');

        $this->setHost('example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            'body',
            'example.com',
            'missing override must fall back to the shared domain/main.html.twig',
        );
    }

    public function testProjectOverrideTemplateReplacesDefaultShell(): void
    {
        $domain = $this->createDomain('example.com');
        $this->createProject('subdomain1', $domain);

        $this->writeTemplate(
            'subdomain/overrides/core/subdomain1.html.twig',
            <<<'TWIG'
            {% extends 'base.html.twig' %}
            {% block body %}
                <h1 data-testid="subdomain-override">subdomain1 bespoke shell</h1>
            {% endblock %}
            TWIG,
        );

        $this->clearTwigCache();
        $this->setHost('subdomain1.example.com');
        $this->client()->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(
            '[data-testid="subdomain-override"]',
            'subdomain1 bespoke shell',
            'subdomain/overrides/<vendor>/<subdomain-id>.html.twig must win over subdomain/main.html.twig',
        );
    }

    private function writeTemplate(string $relativePath, string $contents): void
    {
        $absolutePath = self::getContainer()->getParameter('kernel.project_dir').'/templates/'.$relativePath;
        $directory = dirname($absolutePath);

        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            self::fail(sprintf('Could not create template directory "%s".', $directory));
        }

        if (file_put_contents($absolutePath, $contents) === false) {
            self::fail(sprintf('Could not write template fixture to "%s".', $absolutePath));
        }

        $this->writtenTemplates[] = $absolutePath;
    }

    private function clearTwigCache(): void
    {
        $twig = self::getContainer()->get('twig');
        if ($twig instanceof \Twig\Environment) {
            // Clear the in-memory template cache; the filesystem cache is the
            // `var/cache/test` compiled-template store and Twig re-reads files
            // on demand in dev/test profiles.
            $reflection = new \ReflectionClass($twig);
            foreach (['loadedTemplates', 'optionsHash'] as $property) {
                if ($reflection->hasProperty($property)) {
                    $prop = $reflection->getProperty($property);
                    $prop->setAccessible(true);
                    if ($property === 'loadedTemplates') {
                        $prop->setValue($twig, []);
                    }
                }
            }
        }
    }
}
