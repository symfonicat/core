<?php

namespace Symfonicat\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

trait VendorScopedIdFormTrait
{
    private function addDisabledVendorField(FormBuilderInterface $builder): void
    {
        $builder->add('vendor', null, [
            'label' => 'vendor',
            'disabled' => true,
        ]);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function addVendorPrefixSubmitListener(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event) use ($options): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $id = trim((string) ($data['id'] ?? ''));
            if ($id !== '' && !str_contains($id, '/')) {
                $entity = $event->getForm()->getData();
                $vendor = is_object($entity) && method_exists($entity, 'getVendor')
                    ? trim((string) $entity->getVendor())
                    : '';
                $vendor = $vendor === '' ? trim((string) ($options['default_vendor'] ?? 'core')) : $vendor;
                $data['id'] = $vendor.'/'.$id;
                $event->setData($data);
            }
        });
    }
}
