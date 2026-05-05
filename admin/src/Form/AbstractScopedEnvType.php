<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\EnvRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;

abstract class AbstractScopedEnvType extends AbstractType
{
    protected function buildEnvFields(FormBuilderInterface $builder): void
    {
        $builder
            ->add('envParent', EntityType::class, [
                'class' => EnvParent::class,
                'label' => false,
                'mapped' => false,
                'required' => false,
                'choice_label' => 'id',
                'placeholder' => 'select env parent',
                'query_builder' => static fn (EnvParentRepository $repository) => $repository
                    ->createQueryBuilder('envParent')
                    ->orderBy('envParent.id', 'ASC'),
                'attr' => [
                    'data-env-parent-select' => '',
                    'data-action' => 'change->env-collection#syncFromParent',
                ],
            ])
            ->add('env', EntityType::class, [
                'class' => Env::class,
                'label' => false,
                'choice_label' => 'id',
                'placeholder' => 'select env',
                'query_builder' => static fn (EnvRepository $repository) => $repository
                    ->createQueryBuilder('env')
                    ->leftJoin('env.envParent', 'envParent')
                    ->addSelect('envParent')
                    ->orderBy('envParent.id', 'ASC')
                    ->addOrderBy('env.id', 'ASC'),
                'choice_attr' => static fn (Env $env): array => [
                    'data-env-parent' => (string) $env->getEnvParent()?->getId(),
                ],
                'attr' => [
                    'data-env-select' => '',
                    'data-action' => 'change->env-collection#syncFromEnv',
                ],
            ])
            ->add('value', null, [
                'label' => false,
            ])
        ;

        $builder->addEventListener(FormEvents::POST_SET_DATA, static function (FormEvent $event): void {
            $data = $event->getData();
            $form = $event->getForm();

            if (!is_object($data) || !method_exists($data, 'getEnv') || !$form->has('envParent')) {
                return;
            }

            $selectedEnv = $data->getEnv();
            $selectedEnvParent = $selectedEnv instanceof Env ? $selectedEnv->getEnvParent() : null;
            $form->get('envParent')->setData($selectedEnvParent);
        });
    }
}
