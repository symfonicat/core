<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Middleware;
use Symfonicat\Repository\MiddlewareRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MiddlewareReferenceType extends AbstractType
{
    public function getParent(): string
    {
        return EntityType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'class' => Middleware::class,
            'choice_label' => static fn (Middleware $middleware): string => (string) $middleware->getClass(),
            'choice_value' => static fn (?Middleware $middleware): string => $middleware ? (string) $middleware->getId() : '',
            'label' => false,
            'placeholder' => 'select middleware',
            'query_builder' => static fn (MiddlewareRepository $repository) => $repository
                ->createQueryBuilder('middleware')
                ->orderBy('middleware.class', 'ASC'),
            'required' => false,
        ]);
    }
}
