<?php

namespace Symfonicat\Service;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Form\FileUploadItemType;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicFileUploadService
{
    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function upload(string $name, string $type, ?Domain $domain, ?Subdomain $subdomain, UploadedFile $file): string
    {
        $fileName = $this->normalizeFileName($name);
        $relativeDirectory = match ($type) {
            FileUploadItemType::FILE_TYPE_DOMAIN => $this->domainDirectory($domain),
            FileUploadItemType::FILE_TYPE_SUBDOMAIN => $this->subdomainDirectory($subdomain),
            default => throw new \InvalidArgumentException(sprintf('Unsupported file upload type "%s".', $type)),
        };

        $absoluteDirectory = $this->publicDir.'/'.$relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            throw new \RuntimeException(sprintf('Could not create public upload directory "%s".', $relativeDirectory));
        }

        $file->move($absoluteDirectory, $fileName);

        return $relativeDirectory.'/'.$fileName;
    }

    private function normalizeFileName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || $name === '.' || $name === '..') {
            throw new \InvalidArgumentException('Enter a file name.');
        }

        if (str_contains($name, "\0") || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new \InvalidArgumentException('File name must not contain path separators.');
        }

        return $name;
    }

    private function domainDirectory(?Domain $domain): string
    {
        if (!$domain instanceof Domain || trim((string) $domain->getId(false)) === '') {
            throw new \InvalidArgumentException('Select a domain for each domain file upload.');
        }

        return 'domains/'.$this->normalizeTargetId((string) $domain->getId(false));
    }

    private function subdomainDirectory(?Subdomain $subdomain): string
    {
        if (!$subdomain instanceof Subdomain || trim((string) $subdomain->getAffix()) === '') {
            throw new \InvalidArgumentException('Select a subdomain for each subdomain file upload.');
        }

        return 'subdomains/'.$this->normalizeTargetId((string) $subdomain->getAffix());
    }

    private function normalizeTargetId(string $id): string
    {
        $id = trim($id);
        if ($id === '' || str_contains($id, "\0")) {
            throw new \InvalidArgumentException('Selected upload target has an invalid id.');
        }

        $segments = explode('/', $id);
        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new \InvalidArgumentException('Selected upload target has an invalid id.');
            }
        }

        return implode('/', $segments);
    }
}
