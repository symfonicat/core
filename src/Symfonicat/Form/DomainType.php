<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DomainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', null, [
                'label' => 'domain',
            ])
            ->add('routeOverride', CheckboxType::class, [
                'label' => 'route override',
                'required' => false,
            ])
            ->add('routeName', null, [
                'label' => 'route name',
                'required' => false,
                'label' => false,
                'empty_data' => '',
            ])
            ->add('redirectDomain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'redirect domain',
                'required' => false,
                'placeholder' => 'select a redirect domain',
            ])
            ->add('projects', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn (Project $project): string => $project->getName(),
                'label' => 'projects',
                'multiple' => true,
                'required' => false,
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
                'entry_type' => DomainEnvType::class,
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
            'data_class' => Domain::class,
            'is_admin' => false,
        ]);
    }
}
