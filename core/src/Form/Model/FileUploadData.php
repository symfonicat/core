<?php

namespace Symfonicat\Form\Model;

final class FileUploadData
{
    public string $name = '';

    /**
     * @var list<FileUploadItemData>
     */
    public array $files = [];

    public function __construct()
    {
        $this->files[] = new FileUploadItemData();
    }
}
