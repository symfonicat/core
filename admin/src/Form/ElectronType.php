<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Electron;
use Symfonicat\Entity\Project;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ElectronType extends AbstractType
{
    use VendorScopedIdFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $electron = $builder->getData();
        $electronId = $electron instanceof Electron ? trim((string) $electron->getId(false)) : '';

        $this->addDisabledVendorField($builder);

        $builder
            ->add('id', null, [
                'label' => 'id',
                'disabled' => $electronId !== '' || !$options['id_editable'],
            ])
            ->add('name', null, [
                'label' => 'name',
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'type',
                'choices' => Electron::typeChoices(),
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
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
            ->add('application', EntityType::class, [
                'class' => Application::class,
                'choice_label' => 'id',
                'label' => 'application',
                'required' => false,
                'placeholder' => 'select application',
            ])
            ->add('favicon', FileType::class, [
                'label' => 'favicon',
                'mapped' => false,
                'required' => false,
            ])
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => ElectronEnvType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
        ;

        $this->addVendorPrefixSubmitListener($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Electron::class,
            'id_editable' => true,
            'default_vendor' => 'core',
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
        $resolver->setAllowedTypes('default_vendor', 'string');
    }
}
