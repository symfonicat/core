<?php

namespace Symfonicat\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfonicat\Entity\Parcel;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Repository\ParcelRepository;

final class ParcelService
{
    public function __construct(
        private readonly ParcelRepository $parcelRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PackageDiscoveryService $packageDiscoveryService,
    ) {
    }

    /**
     * @return array{
     *     created: list<array{id: string, path: string}>,
     *     deleted: list<array{id: string, path: string, references: array{domains: int, subdomains: int}}>,
     *     updated: list<array{id: string, from: string, to: string}>
     * }
     */
    public function sync(): array
    {
        $packageParcels = $this->packageDiscoveryService->discoverParcels();
        $databaseParcels = $this->indexDatabaseParcels();

        $created = [];
        $updated = [];
        $deleted = [];

        foreach ($packageParcels as $parcelId => $parcelData) {
            $parcel = $databaseParcels[$parcelId] ?? null;
            if (!$parcel instanceof Parcel) {
                $parcel = (new Parcel())
                    ->setId($parcelId)
                    ->setPath($parcelData['path']);

                $this->entityManager->persist($parcel);
                $databaseParcels[$parcelId] = $parcel;
                $created[] = [
                    'id' => $parcelId,
                    'path' => $parcelData['path'],
                ];

                continue;
            }

            if ($parcel->getPath() !== $parcelData['path']) {
                $updated[] = [
                    'id' => $parcelId,
                    'from' => $parcel->getPath(),
                    'to' => $parcelData['path'],
                ];
                $parcel->setPath($parcelData['path']);
            }
        }

        foreach ($databaseParcels as $parcelId => $parcel) {
            if (isset($packageParcels[$parcelId]) || !$this->isPackageParcelPath($parcel->getPath())) {
                continue;
            }

            $deleted[] = [
                'id' => $parcelId,
                'path' => $parcel->getPath(),
                'references' => $this->clearParcelReferences($parcel),
            ];

            $this->entityManager->remove($parcel);
        }

        $this->entityManager->flush();

        return [
            'created' => $created,
            'deleted' => $deleted,
            'updated' => $updated,
        ];
    }

    private function isPackageParcelPath(string $path): bool
    {
        $path = $this->normalizePath($path);
        if ($path === '') {
            return false;
        }

        foreach ($this->packageDiscoveryService->packageEntryBaseDirectories('parcel') as $baseDirectory) {
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
     * @return array{domains: int, subdomains: int}
     */
    private function clearParcelReferences(Parcel $parcel): array
    {
        return [
            'domains' => $this->clearParcelReference(Domain::class, $parcel),
            'subdomains' => $this->clearParcelReference(Subdomain::class, $parcel),
        ];
    }

    /**
     * @param class-string $entityClass
     */
    private function clearParcelReference(string $entityClass, Parcel $parcel): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->update($entityClass, 'row')
            ->set('row.parcel', 'NULL')
            ->where('row.parcel = :parcel')
            ->setParameter('parcel', $parcel)
            ->getQuery()
            ->execute();
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', $path), " \t\n\r\0\x0B/");
    }

    /**
     * @return array<string, Parcel>
     */
    private function indexDatabaseParcels(): array
    {
        $parcels = [];

        foreach ($this->parcelRepository->findAllOrderedById() as $parcel) {
            $parcelId = $parcel->getId();
            if ($parcelId === null || $parcelId === '') {
                continue;
            }

            $parcels[$parcelId] = $parcel;
        }

        return $parcels;
    }
}
