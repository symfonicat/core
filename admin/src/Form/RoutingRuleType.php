<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Application;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoutingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Reserved-argument enforcement lives on the entity so it fires for
        // every persist path, not just this form.
        $builder
            ->add('arguments', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'regex segment',
                    ],
                ],
                'label' => 'arguments',
                'required' => false,
                'empty_data' => [],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'by_reference' => false,
                'prototype' => true,
                'help_html' => true,
                'help' => sprintf('Regex path segments to match. Reserved: <b>%s</b>.', implode('</b>, <b>', RoutingRule::RESERVED_ARGUMENTS)),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => RoutingRule::getTypeChoices(),
                'label' => 'type',
                'help' => 'Choose domain, project, application, redirect, or route.',
            ])
            ->add('applicationType', ChoiceType::class, [
                'choices' => RoutingRule::getApplicationTypeChoices(),
                'label' => 'application type',
                'required' => false,
                'placeholder' => 'select application type',
                'help' => 'Choose whether the application matches arguments or a Symfony route.',
            ])
            ->add('redirectType', ChoiceType::class, [
                'choices' => RoutingRule::getRedirectTypeChoices(),
                'label' => 'redirect type',
                'required' => false,
                'placeholder' => 'select redirect type',
                'help' => 'Choose whether the redirect rule matches a domain or project.',
            ])
            ->add('routeType', ChoiceType::class, [
                'choices' => RoutingRule::getRouteTypeChoices(),
                'label' => 'route type',
                'required' => false,
                'placeholder' => 'select route type',
                'help' => 'Choose whether the route rule matches a domain or project.',
            ])
            ->add('redirectTarget', ChoiceType::class, [
                'choices' => RoutingRule::getRedirectTargetChoices(),
                'label' => 'redirect target',
                'required' => false,
                'placeholder' => 'select redirect target',
                'help' => 'Choose whether the redirect points to a domain, a project, or a project on a specific domain.',
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'domain',
                'required' => false,
                'placeholder' => 'select a domain',
                'help' => 'Select the domain this rule applies to.',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'id',
                'label' => 'project',
                'required' => false,
                'placeholder' => 'select a project',
                'help' => 'Select the project this rule applies to.',
            ])
            ->add('application', EntityType::class, [
                'class' => Application::class,
                'choice_label' => 'id',
                'label' => 'application',
                'required' => false,
                'placeholder' => 'select an application',
                'help' => 'Select the application this rule renders.',
            ])
            ->add('redirectDomain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'redirect domain',
                'required' => false,
                'placeholder' => 'select a redirect domain',
                'help' => 'Choose the domain to redirect to.',
            ])
            ->add('redirectProject', EntityType::class, [
                'class' => Project::class,
                'choice_label' => 'id',
                'label' => 'redirect project',
                'required' => false,
                'placeholder' => 'select a redirect project',
                'help' => 'Choose the project to redirect to.',
            ])
            ->add('route', null, [
                'label' => 'route',
                'required' => false,
                'help' => 'Enter the Symfony route name to render or attach an application to.',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RoutingRule::class,
        ]);
    }
}
