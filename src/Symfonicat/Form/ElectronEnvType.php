<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\ElectronEnv;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ElectronEnvType extends AbstractScopedEnvType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->buildEnvFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => ElectronEnv::class,
        ]);
    }
}
