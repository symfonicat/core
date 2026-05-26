<?php

namespace Symfonicat\Form\Model;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadItemData
{
    public string $type = 'domain';

    public ?Domain $domain = null;

    public ?Subdomain $subdomain = null;

    public ?UploadedFile $file = null;
}
