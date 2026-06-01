<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Parcel;

trait ParcelChoiceFormTrait
{
    private static function parcelChoiceLabel(Parcel $parcel): string
    {
        $id = trim((string) $parcel->getId());
        if ($id === '') {
            return '';
        }

        $parts = explode('/', $id);

        return (string) end($parts);
    }

    private static function parcelChoiceGroup(Parcel $parcel): string
    {
        $id = trim((string) $parcel->getId());
        if ($id === '') {
            return '';
        }

        $parts = explode('/', $id);
        array_pop($parts);

        return implode('/', $parts);
    }
}
