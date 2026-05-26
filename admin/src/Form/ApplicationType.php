<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Subdomain;
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

        $builder
            ->add('id', null, [
                'label' => 'id',
                'disabled' => $applicationId !== '' || !$options['id_editable'],
            ])
            ->add('name', null, [
                'label' => 'name',
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'domain',
                'required' => true,
                'placeholder' => 'select domain',
            ])
            ->add('subdomain', EntityType::class, [
                'class' => Subdomain::class,
                'choice_label' => static fn (Subdomain $subdomain): string => (string) $subdomain->getId(),
                'label' => 'subdomain',
                'required' => false,
                'placeholder' => 'select subdomain',
            ])
            ->add('endpoint', EntityType::class, [
                'class' => Endpoint::class,
                'choice_label' => static fn (Endpoint $endpoint): string => (string) $endpoint->getId(),
                'label' => 'endpoint',
                'required' => false,
                'placeholder' => 'select endpoint',
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
