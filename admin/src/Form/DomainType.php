<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Subdomain;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class DomainType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', null, [
                'label' => 'domain',
            ])
            ->add('subdomains', EntityType::class, [
                'class' => Subdomain::class,
                'choice_label' => static fn (Subdomain $subdomain): string => (string) $subdomain->getId(),
                'label' => 'subdomains',
                'multiple' => true,
                'required' => false,
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
            ->add('env', CollectionType::class, [
                'label' => 'env',
                'entry_type' => DomainEnvType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'required' => false,
                'prototype' => true,
            ])
        ;

    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Domain::class,
            'is_admin' => false,
        ]);
    }
}
