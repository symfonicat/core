<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Parcel;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Module;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\Subdomain;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class EndpointType extends AbstractType
{
    use ParcelChoiceFormTrait;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $endpoint = $builder->getData();
        $endpointId = $endpoint instanceof Endpoint ? trim((string) $endpoint->getId()) : '';

        if ($endpointId === '') {
            $builder->add('id', null, [
                'label' => 'id',
                'disabled' => !$options['id_editable'],
            ]);
        }

        $builder
            ->add('enforce', ChoiceType::class, [
                'label' => 'enforce',
                'choices' => [
                    'domain' => Endpoint::ENFORCE_DOMAIN,
                    'subdomain' => Endpoint::ENFORCE_SUBDOMAIN,
                ],
                'required' => false,
                'placeholder' => 'select enforce',
            ])
            ->add('domain', EntityType::class, [
                'class' => Domain::class,
                'choice_label' => static fn (Domain $d): string => (string) $d->getTld(),
                'label' => 'domain',
                'placeholder' => 'select domain',
                'required' => false,
            ])
            ->add('subdomain', EntityType::class, [
                'class' => Subdomain::class,
                'choice_label' => static function (Subdomain $subdomain): string {
                    $affix = trim((string) $subdomain->getAffix());
                    $domain = trim((string) $subdomain->getDomain()?->getTld());

                    return $domain === '' ? $affix : sprintf('%s (%s)', $affix, $domain);
                },
                'label' => 'subdomain',
                'placeholder' => 'select subdomain',
                'required' => false,
            ])
            ->add('parcel', EntityType::class, [
                'class' => Parcel::class,
                'choice_label' => self::parcelChoiceLabel(...),
                'group_by' => self::parcelChoiceGroup(...),
                'label' => 'parcel',
                'placeholder' => 'select parcel',
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
                'choice_value' => static fn (?Module $module): string => $module ? (string) $module->getId() : '',
                'label' => 'modules',
                'multiple' => true,
                'by_reference' => false,
                'required' => false,
            ])
            ->add('catch', CheckboxType::class, [
                'label' => 'catch',
                'required' => false,
                'false_values' => [null, ''],
            ])
            ->add('middlewares', CollectionType::class, [
                'entry_type' => MiddlewareReferenceType::class,
                'label' => 'middlewares',
                'required' => false,
                'empty_data' => [],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('env', CollectionType::class, [
                'entry_type' => EndpointEnvType::class,
                'label' => 'env',
                'required' => false,
                'empty_data' => [],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
            ->add('arguments', CollectionType::class, [
                'entry_type' => TextType::class,
                'entry_options' => [
                    'label' => false,
                    'attr' => [
                        'placeholder' => 'regex segment',
                    ],
                ],
                'label' => 'arguments',
                'required' => false,
                'empty_data' => [],
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => true,
                'by_reference' => false,
                'prototype' => true,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Endpoint::class,
            'id_editable' => true,
        ]);

        $resolver->setAllowedTypes('id_editable', 'bool');
    }
}
