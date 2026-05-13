<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
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
    public const FILE_TYPE_PROJECT = 'project';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'type',
                'choices' => [
                    'domain' => self::FILE_TYPE_DOMAIN,
                    'project' => self::FILE_TYPE_PROJECT,
                ],
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => static fn (Domain $domain): string => (string) $domain->getId(),
                'label' => 'domain',
                'required' => false,
                'placeholder' => 'select domain',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn (Project $project): string => (string) $project->getId(),
                'label' => 'project',
                'required' => false,
                'placeholder' => 'select project',
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
