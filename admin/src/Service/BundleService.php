<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\Bundle;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\BundleRepository;

final class BundleService
{
    public function __construct(
        private readonly BundleRepository $bundleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
    }

    /**
     * @return array{
     *     created: list<array{id: string, path: string}>,
     *     deleted: list<array{id: string, path: string, references: array{applications: int, domains: int, subdomains: int}}>,
     *     updated: list<array{id: string, from: string, to: string}>
     * }
     */
    public function sync(): array
    {
        $packageBundles = $this->packageDiscoveryService->discoverBundles();
        $databaseBundles = $this->indexDatabaseBundles();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($packageBundles as $bundleId => $bundleData) {
            $bundle = $databaseBundles[$bundleId] ?? null;
            if (!$bundle instanceof Bundle) {
                $bundle = (new Bundle())
                    ->setId($bundleId)
                    ->setPath($bundleData['path']);

                $this->entityManager->persist($bundle);
                $databaseBundles[$bundleId] = $bundle;
                $created[] = [
                    'id' => $bundleId,
                    'path' => $bundleData['path'],
                ];

                continue;
            }

            if ($bundle->getPath() !== $bundleData['path']) {
                $updated[] = [
                    'id' => $bundleId,
                    'from' => $bundle->getPath(),
                    'to' => $bundleData['path'],
                ];
                $bundle->setPath($bundleData['path']);
            }
        }

        foreach ($databaseBundles as $bundleId => $bundle) {
            if (isset($packageBundles[$bundleId]) || !$this->isPackageBundlePath($bundle->getPath())) {
                continue;
            }

            $deleted[] = [
                'id' => $bundleId,
                'path' => $bundle->getPath(),
                'references' => $this->clearBundleReferences($bundle),
            ];

            $this->entityManager->remove($bundle);
        }

        $this->entityManager->flush();

        return [
            'created' => $created,
            'deleted' => $deleted,
            'updated' => $updated,
        ];
    }

    private function isPackageBundlePath(string $path): bool
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return false;
        }

        foreach ($this->packageDiscoveryService->packageEntryBaseDirectories('bundle') as $baseDirectory) {
            $relativeBasePath = rtrim($this->normalizePath($baseDirectory['relative']), '/');
            $absoluteBasePath = rtrim($this->normalizePath($baseDirectory['absolute']), '/');

            if (
                $path === $relativeBasePath
                || str_starts_with($path, $relativeBasePath.'/')
                || $path === $absoluteBasePath
                || str_starts_with($path, $absoluteBasePath.'/')
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{applications: int, domains: int, subdomains: int}
     */
    private function clearBundleReferences(Bundle $bundle): array
    {
        return [
            'applications' => $this->clearBundleReference(Application::class, $bundle),
            'domains' => $this->clearBundleReference(Domain::class, $bundle),
            'subdomains' => $this->clearBundleReference(Subdomain::class, $bundle),
        ];
    }

    /**
     * @param class-string $entityClass
     */
    private function clearBundleReference(string $entityClass, Bundle $bundle): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->update($entityClass, 'row')
            ->set('row.bundle', 'NULL')
            ->where('row.bundle = :bundle')
            ->setParameter('bundle', $bundle)
            ->getQuery()
            ->execute();
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), " \t\n\r\0\x0B/");
    }

    /**
     * @return array<string, Bundle>
     */
    private function indexDatabaseBundles(): array
    {
        $bundles = [];

        foreach ($this->bundleRepository->findAllOrderedById() as $bundle) {
            $bundleId = $bundle->getId();
            if ($bundleId === null || $bundleId === '') {
                continue;
            }

            $bundles[$bundleId] = $bundle;
        }

        return $bundles;
    }
}
