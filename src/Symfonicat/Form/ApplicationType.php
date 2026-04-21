<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Module;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ApplicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $application = $builder->getData();
        $applicationId = $application instanceof Application ? trim((string) $application->getId()) : '';

        if ($applicationId === '') {
            $builder->add('id', null, [
                'label' => 'id',
                'disabled' => !$options['id_editable'],
            ]);
        }

        $builder
            ->add('modules', EntityType::class, [
                'class' => Module::class,
                'choice_label' => static fn (Module $module): string => $module->getName() ?? (string) $module->getId(),
                'label' => 'modules',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => ApplicationEnvType::class,
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
            'data_class' => Application::class,
            'id_editable' => true,
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
    }
}
