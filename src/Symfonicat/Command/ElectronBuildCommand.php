<?php

namespace Symfonicat\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;
use Symfonicat\Repository\ElectronRepository;
use Symfonicat\Service\ApplicationService;
use Twig\Environment;
use Twig\Error\LoaderError;

#[AsCommand(
    name: 'symfonicat:electron:build',
    description: 'Build Electron applications from Electron entity rows.',
    aliases: ['electron:build'],
)]
final class ElectronBuildCommand extends Command
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly ElectronRepository $electronRepository,
        private readonly Environment $twig,
        private readonly Filesystem $filesystem,
        #[Autowire('%symfonicat.asset_base_url%')]
        private readonly string $assetBaseUrl,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('name', InputArgument::OPTIONAL, 'Limit the build to one Electron row name.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $requestedName = trim((string) $input->getArgument('name'));

        $electrons = $this->electronRepository->findAllOrdered();
        if ($requestedName !== '') {
            $electrons = array_values(array_filter($electrons, static fn (Electron $electron): bool => $electron->getName() === $requestedName));
        }

        if ($electrons === []) {
            $io->warning($requestedName === '' ? 'No Electron rows found.' : sprintf('No Electron row named "%s" was found.', $requestedName));

            return Command::SUCCESS;
        }

        $success = true;

        foreach ($electrons as $electron) {
            $targetId = trim((string) $electron->getTargetId());
            if ($targetId === '') {
                $io->warning(sprintf('Skipping Electron "%s" because its %s target is missing.', (string) $electron->getName(), $electron->getType()));
                $success = false;

                continue;
            }

            $targetDir = sprintf('%s/electron/%s/%s', $this->projectDir, $electron->getType(), $targetId);
            $this->filesystem->mkdir($targetDir);
            $this->filesystem->remove([
                $targetDir.'/app.js',
                $targetDir.'/package.json',
                $targetDir.'/icon.png',
                $targetDir.'/build',
            ]);

            $iconPath = null;
            $publicFavicon = $electron->getFavicon();
            if (is_string($publicFavicon) && $publicFavicon !== '') {
                $sourceIcon = $this->projectDir.'/public/'.ltrim($publicFavicon, '/');
                if (is_file($sourceIcon)) {
                    $this->filesystem->copy($sourceIcon, $targetDir.'/icon.png', true);
                    $iconPath = 'icon.png';
                }
            }

            $template = $this->resolveTemplate($electron, $targetId);
            $startUrl = $this->resolveStartUrl($electron);

            $rendered = $this->twig->render($template, [
                'electron_config' => $electron,
                'icon_path' => $iconPath,
                'start_url' => $startUrl,
                'target_id' => $targetId,
            ]);
            file_put_contents($targetDir.'/app.js', $rendered);
            file_put_contents($targetDir.'/package.json', json_encode($this->packageManifest($electron, $targetId, $iconPath !== null), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $process = new Process([
                'npx',
                'electron-builder',
                '--projectDir',
                $targetDir,
                '--dir',
                '--config.directories.output=build',
            ], $this->projectDir);
            $process->setTimeout(null);
            $process->run(static function (string $type, string $buffer) use ($output): void {
                $output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $io->error(sprintf('Electron build failed for %s "%s".', $electron->getType(), $targetId));
                $success = false;

                continue;
            }

            $io->success(sprintf('Built Electron %s "%s" into %s.', $electron->getType(), $targetId, $targetDir.'/build'));
        }

        return $success ? Command::SUCCESS : Command::FAILURE;
    }

    private function resolveTemplate(Electron $electron, string $targetId): string
    {
        $override = sprintf('electron/%s/overrides/%s.twig.js', $electron->getType(), $targetId);

        try {
            $this->twig->load($override);

            return $override;
        } catch (LoaderError) {
            return sprintf('electron/%s/main.twig.js', $electron->getType());
        }
    }

    private function resolveStartUrl(Electron $electron): string
    {
        $baseUrl = $this->normalizedBaseUrl();

        return match ($electron->getType()) {
            Electron::TYPE_DOMAIN => $this->appendElectronQuery($this->urlForHost($this->shortId($electron->getDomain()?->getId() ?? ''), '/')),
            Electron::TYPE_PROJECT => $this->appendElectronQuery($this->urlForHost($this->projectHost($electron), '/')),
            Electron::TYPE_APPLICATION => $this->appendElectronQuery($baseUrl.$this->applicationService->path($electron->getApplication() ?? throw new \RuntimeException('Application is required for Electron application builds.'))),
            default => $this->appendElectronQuery($baseUrl.'/'),
        };
    }

    private function normalizedBaseUrl(): string
    {
        $baseUrl = trim($this->assetBaseUrl);
        if (preg_match('#^https?://#i', $baseUrl) === 1) {
            return rtrim($baseUrl, '/');
        }

        return 'http://localhost';
    }

    private function urlForHost(string $host, string $path): string
    {
        $host = trim($host);
        if ($host === '') {
            throw new \RuntimeException('Electron host could not be resolved.');
        }

        $base = parse_url($this->normalizedBaseUrl());
        $scheme = is_string($base['scheme'] ?? null) && $base['scheme'] !== '' ? $base['scheme'] : 'http';
        $port = is_int($base['port'] ?? null) ? ':'.$base['port'] : '';

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
    }

    private function appendElectronQuery(string $url): string
    {
        return str_contains($url, '?') ? $url.'&electron' : $url.'?electron';
    }

    private function projectHost(Electron $electron): string
    {
        $project = $electron->getProject();
        if (!$project instanceof Project) {
            throw new \RuntimeException('Project is required for Electron project builds.');
        }

        $projectId = $this->shortId(trim((string) $project->getId()));
        if ($projectId === '') {
            throw new \RuntimeException('Project id is required for Electron project builds.');
        }

        $domainId = $this->shortId(trim((string) $electron->getDomain()?->getId()));
        if ($domainId === '') {
            throw new \RuntimeException('Domain is required for Electron project builds.');
        }

        return sprintf('%s.%s', $projectId, $domainId);
    }

    private function shortId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            return '';
        }

        if (strpos($id, '/') === false) {
            return $id;
        }

        $parts = explode('/', $id);

        return (string) end($parts);
    }

    /**
     * @return array<string, mixed>
     */
    private function packageManifest(Electron $electron, string $targetId, bool $hasIcon): array
    {
        $electronVersion = $this->rootElectronVersion();

        $package = [
            'name' => strtolower(sprintf('symfonicat-%s-%s', $electron->getType(), preg_replace('/[^a-zA-Z0-9._-]+/', '-', $targetId) ?? $targetId)),
            'version' => '1.0.0',
            'description' => sprintf('Symfonicat Electron wrapper for %s "%s".', $electron->getType(), $targetId),
            'author' => 'Symfonicat',
            'private' => true,
            'main' => 'app.js',
            'build' => [
                'appId' => strtolower(sprintf('com.symfonicat.%s.%s', $electron->getType(), preg_replace('/[^a-zA-Z0-9]+/', '', $targetId) ?? $targetId)),
                'electronVersion' => $electronVersion,
                'directories' => [
                    'output' => 'build',
                ],
                'files' => ['app.js'],
            ],
        ];

        if ($hasIcon) {
            $package['build']['icon'] = 'icon.png';
            $package['build']['files'][] = 'icon.png';
        }

        return $package;
    }

    private function rootElectronVersion(): string
    {
        $packageJsonPath = $this->projectDir.'/package.json';
        $raw = file_get_contents($packageJsonPath);
        if (!is_string($raw) || $raw === '') {
            throw new \RuntimeException('Unable to read the root package.json for Electron version metadata.');
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unable to decode the root package.json for Electron version metadata.');
        }

        $electronConstraint = $decoded['devDependencies']['electron'] ?? $decoded['dependencies']['electron'] ?? null;
        if (!is_string($electronConstraint) || trim($electronConstraint) === '') {
            throw new \RuntimeException('The root package.json does not declare an Electron dependency.');
        }

        if (!preg_match('/\d+\.\d+\.\d+/', $electronConstraint, $matches)) {
            throw new \RuntimeException(sprintf('Unable to derive a fixed Electron version from "%s".', $electronConstraint));
        }

        return $matches[0];
    }
}
