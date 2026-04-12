<?php

namespace Symfonicat\Service;

use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadService
{
    public function __construct(
        private readonly string $publicDir,
    ) {
    }

    public function uploadPublicImageAsPng(UploadedFile $file, string $path): string
    {
        $path = ltrim($path, '/');
        if ($path === '' || !str_ends_with(strtolower($path), '.png')) {
            throw new \InvalidArgumentException('Image path must end with .png');
        }

        $png = $this->convertUploadedImageToPng($file);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Unable to create upload stream.');
        }

        try {
            fwrite($stream, $png);
            rewind($stream);
            $this->writeLocalStream($path, $stream);
        } finally {
            fclose($stream);
        }

        return $path;
    }

    private function writeLocalStream(string $path, $stream): void
    {
        $target = rtrim($this->publicDir, '/') . '/' . ltrim($path, '/');
        $dir = dirname($target);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $out = fopen($target, 'w');
        if ($out === false) {
            throw new \RuntimeException('Unable to write local file.');
        }

        try {
            stream_copy_to_stream($stream, $out);
        } finally {
            fclose($out);
        }
    }

    private function convertUploadedImageToPng(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if (!is_string($realPath) || $realPath === '') {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        $raw = file_get_contents($realPath);
        if ($raw === false) {
            throw new \RuntimeException('Unable to read uploaded file.');
        }

        if ($this->isPngImage($raw)) {
            return $raw;
        }

        if (class_exists(\Imagick::class)) {
            try {
                $imagick = new \Imagick();
                $imagick->readImageBlob($raw);
                $imagick->setImageFormat('png');
                $png = $imagick->getImagesBlob();
                $imagick->clear();
                $imagick->destroy();

                if (is_string($png) && $png !== '') {
                    return $png;
                }
            } catch (\Throwable) {
                // Fall through to GD conversion if available.
            }
        }

        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new \RuntimeException('Upload a PNG icon, or install GD/Imagick to convert other formats.');
        }

        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            throw new \RuntimeException('Uploaded file is not a valid image.');
        }

        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        if (!is_string($png)) {
            throw new \RuntimeException('Unable to convert uploaded image to PNG.');
        }

        return $png;
    }

    private function isPngImage(string $raw): bool
    {
        return str_starts_with($raw, "\x89PNG\r\n\x1a\n");
    }
}
