<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Form\Model\FileUploadItemData;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class FileUploadItemType extends AbstractType
{
    public const FILE_TYPE_DOMAIN = 'domain';
    public const FILE_TYPE_PROJECT = 'subdomain';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'type',
                'choices' => [
                    'domain' => self::FILE_TYPE_DOMAIN,
                    'subdomain' => self::FILE_TYPE_PROJECT,
                ],
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => static fn (Domain $domain): string => (string) $domain->getId(false),
                'label' => 'domain',
                'required' => false,
                'placeholder' => 'select domain',
            ])
            ->add('subdomain', EntityType::class, [
                'class' => Subdomain::class,
                'choice_label' => static fn (Subdomain $subdomain): string => (string) $subdomain->getId(false),
                'label' => 'subdomain',
                'required' => false,
                'placeholder' => 'select subdomain',
            ])
            ->add('file', FileType::class, [
                'label' => 'file',
                'required' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => FileUploadItemData::class,
        ]);
    }
}
