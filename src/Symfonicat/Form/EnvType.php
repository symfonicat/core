<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Repository\EnvParentRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EnvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('envParent', EntityType::class, [
                'class' => EnvParent::class,
                'choice_label' => 'id',
                'label' => 'env parent',
                'placeholder' => 'select env parent',
                'query_builder' => static fn (EnvParentRepository $repository) => $repository
                    ->createQueryBuilder('envParent')
                    ->orderBy('envParent.id', 'ASC'),
            ])
            ->add('id', null, [
                'label' => 'env',
                'disabled' => $options['is_edit'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Env::class,
            'is_edit' => false,
        ]);

        $resolver->setAllowedTypes('is_edit', 'bool');
    }
}
