<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Bundle;
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
     *     updated: list<array{id: string, from: string, to: string}>
     * }
     */
    public function sync(): array
    {
        $packageBundles = $this->packageDiscoveryService->discoverBundles();
        $databaseBundles = $this->indexDatabaseBundles();

        $created = [];
        $updated = [];

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

        $this->entityManager->flush();

        return [
            'created' => $created,
            'updated' => $updated,
        ];
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
