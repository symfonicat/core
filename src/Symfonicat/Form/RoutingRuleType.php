<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoutingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Reserved-argument enforcement lives on the entity
        // (RoutingRule::validateArgument) so it fires for every persist path,
        // not just this form. Duplicating it as a form-level Assert\Regex used
        // to make the UI render the violation twice.
        $builder
            ->add('argument', null, [
                'label' => 'argument',
                'required' => false,
                'empty_data' => '',
                'help_html' => TRUE,
                'help' => sprintf('The path argument to inverse default <b>Project</b> <code>client-side</code> or <b>Domain</b> <code>server-side</code> routing for<br /><b>%s</b> is reserved', RoutingRule::RESERVED_ARGUMENT),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => RoutingRule::getTypeChoices(),
                'label' => 'type',
                'help' => 'Choose domain, project, redirect, or route.',
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
                'help' => 'Choose whether the redirect points to a domain or project.',
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
                'choice_label' => static fn (Project $project): string => sprintf('%s %s', $project->getId(), $project->getName()),
                'label' => 'project',
                'required' => false,
                'placeholder' => 'select a project',
                'help' => 'Select the project this rule applies to.',
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
                'choice_label' => static fn (Project $project): string => sprintf('%s %s', $project->getId(), $project->getName()),
                'label' => 'redirect project',
                'required' => false,
                'placeholder' => 'select a redirect project',
                'help' => 'Choose the project to redirect to.',
            ])
            ->add('route', null, [
                'label' => 'route',
                'required' => false,
                'help' => 'Enter the Symfony route name to render.',
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
