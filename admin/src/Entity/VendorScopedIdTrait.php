<?php

namespace Symfonicat\Entity;

use Doctrine\ORM\Mapping as ORM;

trait VendorScopedIdTrait
{
    #[ORM\Column(length: 100)]
    private string $vendor = 'core';

    public function getId(bool $includeVendor = false): ?string
    {
        if ($this->id === null) {
            return null;
        }

        return $includeVendor ? $this->id : self::idWithoutVendorPrefix($this->id);
    }

    public function setId(string $id): static
    {
        [$vendor, $cleanId] = self::splitVendorId($id);

        $this->vendor = $vendor;
        $this->id = $vendor.'/'.$cleanId;

        return $this;
    }

    public function getVendor(): string
    {
        return $this->vendor;
    }

    public function setVendor(string $vendor): static
    {
        $vendor = self::normalizeVendor($vendor);
        $cleanId = $this->id === null ? null : self::idWithoutVendorPrefix($this->id);

        $this->vendor = $vendor;

        if ($cleanId !== null && $cleanId !== '') {
            $this->id = $vendor.'/'.$cleanId;
        }

        return $this;
    }

    private static function idWithoutVendorPrefix(?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $id = trim($id);
        if ($id === '') {
            return '';
        }

        if (!str_contains($id, '/')) {
            return $id;
        }

        return substr($id, strpos($id, '/') + 1);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function splitVendorId(string $id): array
    {
        $id = trim($id);
        if ($id === '' || !str_contains($id, '/')) {
            throw new \InvalidArgumentException(sprintf('Vendor-prefixed id required, got "%s".', $id));
        }

        [$vendor, $cleanId] = explode('/', $id, 2);
        $vendor = self::normalizeVendor($vendor);
        $cleanId = trim($cleanId);

        if ($cleanId === '') {
            throw new \InvalidArgumentException(sprintf('Vendor-prefixed id "%s" must include a non-empty id after the vendor.', $id));
        }

        return [$vendor, $cleanId];
    }

    private static function normalizeVendor(string $vendor): string
    {
        $vendor = trim($vendor);
        if ($vendor === '' || str_contains($vendor, '/')) {
            throw new \InvalidArgumentException(sprintf('Vendor must be a non-empty composer vendor name, got "%s".', $vendor));
        }

        return $vendor;
    }
}
