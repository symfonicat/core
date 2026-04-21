<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Repository\EnvRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ApplicationEnvType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('env', EntityType::class, [
                'class' => Env::class,
                'label' => false,
                'choice_label' => 'id',
                'placeholder' => 'select env',
                'query_builder' => static fn (EnvRepository $repository) => $repository
                    ->createQueryBuilder('env')
                    ->orderBy('env.id', 'ASC'),
            ])
            ->add('value', null, [
                'label' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ApplicationEnv::class,
        ]);
    }
}
