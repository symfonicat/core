<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Parcel;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SubdomainType extends AbstractType
{
    use VendorScopedIdFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {

        $this->addDisabledVendorField($builder);

        $builder->add('id', null, [
            'label' => 'subdomain',
        ]);

        $builder
            ->add('parcel', EntityType::class, [
                'class' => Parcel::class,
                'choice_label' => static fn (Parcel $parcel): string => (string) $parcel->getId(),
                'label' => 'parcel',
                'placeholder' => 'select parcel',
                'required' => false,
            ])
            ->add('catch', CheckboxType::class, [
                'label' => 'catch',
                'required' => false,
                'false_values' => [null, ''],
            ])
            ->add('modules', EntityType::class, [
                'class' => Module::class,
                'choice_label' => static fn (Module $module): string => (function (Module $m): string {
                    $id = (string) $m->getId();
                    $parts = explode('/', $id);
                    return (string) end($parts);
                })($module),
                'group_by' => static fn (Module $m): string => (function (Module $mod): string {
                    $vendor = trim($mod->getVendor());
                    $package = trim((string) ($mod->getPackage() ?? ''));
                    if ($package === '') {
                        $id = (string) $mod->getId();
                        $parts = explode('/', $id);
                        $package = $parts[1] ?? '';
                    }
                    return $package === '' ? $vendor : sprintf('%s/%s', $vendor, $package);
                })($m),
                'choice_value' => static fn (?Module $m): string => $m ? (string) $m->getId() : '',
                'label' => 'modules',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('middlewares', CollectionType::class, [
                'label' => 'middlewares',
                'entry_type' => MiddlewareReferenceType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => SubdomainEnvType::class,
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
            'data_class' => Subdomain::class,
            'id_editable' => true,
            'default_vendor' => 'core',
            'is_admin' => false,
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
        $resolver->setAllowedTypes('default_vendor', 'string');
        $resolver->setAllowedTypes('is_admin', 'bool');
    }
}
