<?php

namespace App\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Form\FileUploadItemType;
use Symfonicat\Service\PublicFileUploadService;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class PublicFileUploadServiceTest extends TestCase
{
    private string $publicDir;

    protected function setUp(): void
    {
        $this->publicDir = sys_get_temp_dir().'/symfonicat-public-upload-test-'.bin2hex(random_bytes(6));
        mkdir($this->publicDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->publicDir);
    }

    public function testUploadsDomainFileIntoCleanDomainPublicFolder(): void
    {
        $domain = (new Domain())->setId('example.com');
        $file = $this->uploadedFile('domain content');

        $path = (new PublicFileUploadService($this->publicDir))->upload(
            'file.txt',
            FileUploadItemType::FILE_TYPE_DOMAIN,
            $domain,
            null,
            $file,
        );

        self::assertSame('domains/example.com/file.txt', $path);
        self::assertSame('domain content', file_get_contents($this->publicDir.'/'.$path));
    }

    public function testUploadsProjectFileIntoCleanProjectPublicFolder(): void
    {
        $subdomain = (new Project())->setId('core/subdomain1');
        $file = $this->uploadedFile('subdomain content');

        $path = (new PublicFileUploadService($this->publicDir))->upload(
            'file.txt',
            FileUploadItemType::FILE_TYPE_PROJECT,
            null,
            $subdomain,
            $file,
        );

        self::assertSame('subdomains/subdomain1/file.txt', $path);
        self::assertSame('subdomain content', file_get_contents($this->publicDir.'/'.$path));
    }

    public function testRejectsFileNamesWithPathSeparators(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File name must not contain path separators.');

        (new PublicFileUploadService($this->publicDir))->upload(
            '../file.txt',
            FileUploadItemType::FILE_TYPE_DOMAIN,
            (new Domain())->setId('example.com'),
            null,
            $this->uploadedFile('content'),
        );
    }

    private function uploadedFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'symfonicat-upload-source-');
        self::assertIsString($path);

        file_put_contents($path, $contents);

        return new UploadedFile($path, 'source.txt', null, null, true);
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());

                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($path);
    }
}
