<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\SubdomainEnv;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SubdomainEnvType extends AbstractScopedEnvType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $this->buildEnvFields($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SubdomainEnv::class,
        ]);
    }
}
