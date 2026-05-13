<?php

namespace Symfonicat\Form;

use Symfonicat\Form\Model\FileUploadData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FileUploadType extends AbstractType
{
    public const FILE_TYPE_DOMAIN = FileUploadItemType::FILE_TYPE_DOMAIN;
    public const FILE_TYPE_PROJECT = FileUploadItemType::FILE_TYPE_PROJECT;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'name',
            ])
            ->add('files', CollectionType::class, [
                'label' => 'files',
                'entry_type' => FileUploadItemType::class,
                'allow_add' => true,
                'allow_delete' => false,
                'by_reference' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FileUploadData::class,
        ]);
    }
}
