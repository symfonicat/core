<?php

namespace Symfonicat\Form;

use Symfonicat\Entity\Middleware;
use Symfonicat\Service\MiddlewareClassProvider;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class MiddlewareType extends AbstractType
{
    public function __construct(
        private readonly MiddlewareClassProvider $middlewareClassProvider,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', TextType::class, [
                'label' => 'id',
                'disabled' => true,
            ])
            ->add('class', ChoiceType::class, [
                'label' => 'class',
                'disabled' => true,
                'choices' => $this->middlewareClassProvider->choices(),
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Middleware::class,
        ]);
    }
}
