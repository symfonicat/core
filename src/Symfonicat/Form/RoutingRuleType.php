<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\RoutingRule;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RoutingRuleType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', null, [
                'label' => 'slug',
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => 'id',
                'label' => 'domain',
                'required' => false,
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
