<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Bundle;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class BundleType extends AbstractType
{
    use VendorScopedIdFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $bundle = $builder->getData();
        $bundleId = $bundle instanceof Bundle ? trim((string) $bundle->getId(false)) : '';

        $this->addDisabledVendorField($builder);

        if ($bundleId === '') {
            $builder->add('id', null, [
                'label' => 'id',
                'disabled' => !$options['id_editable'],
            ]);
        }

        $builder
            ->add('path', null, [
                'label' => 'path',
            ])
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => BundleEnvType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
        ;

        $this->addVendorPrefixSubmitListener($builder, $options);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Bundle::class,
            'id_editable' => true,
            'default_vendor' => 'core',
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
        $resolver->setAllowedTypes('default_vendor', 'string');
    }
}
