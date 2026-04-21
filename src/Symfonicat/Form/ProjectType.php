<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $project = $builder->getData();
        $projectId = $project instanceof Project ? trim((string) $project->getId()) : '';

        if ($projectId === '') {
            $builder->add('id', null, [
                'label' => 'id',
                'disabled' => !$options['id_editable'],
            ]);
        }

        $builder
            ->add('name', null, [
                'label' => $projectId === '' ? 'name' : sprintf('name (%s)', $projectId),
            ])
            ->add('icon', FileType::class, [
                'label' => 'icon',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(
                        maxSize: '10M',
                        mimeTypes: ['image/*'],
                    ),
                ],
                'attr' => [
                    'accept' => 'image/*',
                ],
            ])
            ->add('modules', EntityType::class, [
                'class' => Module::class,
                'choice_label' => static fn (Module $module): string => $module->getName(),
                'label' => 'modules',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => ProjectEnvType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'id_editable' => true,
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
    }
}
