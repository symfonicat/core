<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\ParcelEnv;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ParcelEnvType extends AbstractScopedEnvType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->buildEnvFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ParcelEnv::class,
        ]);
    }
}
