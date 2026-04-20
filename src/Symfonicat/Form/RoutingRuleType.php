<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\RoutingRule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoutingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('argument', null, [
                'label' => 'argument',
                'help' => sprintf('"%s" is reserved', RoutingRule::RESERVED_ARGUMENT),
                'constraints' => [
                    new Assert\Regex(
                        pattern: sprintf('/^%s$/i', preg_quote(RoutingRule::RESERVED_ARGUMENT, '/')),
                        match: false,
                        message: sprintf('The routing rule argument "%s" is reserved.', RoutingRule::RESERVED_ARGUMENT),
                        normalizer: static fn ($value): string => is_string($value) ? strtolower(trim($value)) : '',
                    ),
                ],
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
