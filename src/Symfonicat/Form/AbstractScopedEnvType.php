<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Env;
use Symfonicat\Entity\EnvParent;
use Symfonicat\Repository\EnvParentRepository;
use Symfonicat\Repository\EnvRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

abstract class AbstractScopedEnvType extends AbstractType
{
    protected function buildEnvFields(FormBuilderInterface $builder): void
    {
        $data = $builder->getData();
        $selectedEnv = is_object($data) && method_exists($data, 'getEnv') ? $data->getEnv() : null;
        $selectedEnvParent = $selectedEnv instanceof Env ? $selectedEnv->getEnvParent() : null;

        $builder
            ->add('envParent', EntityType::class, [
                'class' => EnvParent::class,
                'label' => false,
                'mapped' => false,
                'required' => false,
                'choice_label' => 'id',
                'placeholder' => 'select env parent',
                'data' => $selectedEnvParent,
                'query_builder' => static fn (EnvParentRepository $repository) => $repository
                    ->createQueryBuilder('envParent')
                    ->orderBy('envParent.id', 'ASC'),
                'attr' => [
                    'data-env-parent-select' => '',
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
                ],
            ])
            ->add('value', null, [
                'label' => false,
            ])
        ;
    }
}
