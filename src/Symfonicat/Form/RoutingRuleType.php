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
                'help' => sprintf('"%s" is reserved', RoutingRule::RESERVED_ARGUMENT),
            ])
            ->add('type', ChoiceType::class, [
                'choices' => RoutingRule::getTypeChoices(),
                'label' => 'type',
                'help' => 'whether this rule targets a domain or a project.',
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'domain',
                'required' => false,
                'help' => 'domain to force client-side routing for argument',
            ])
            ->add('project', EntityType::class, [
                'class' => Project::class,
                'choice_label' => static fn (Project $project): string => $project->getName(),
                'label' => 'project',
                'required' => false,
                'help' => 'project to force server-side routing for argument',
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
