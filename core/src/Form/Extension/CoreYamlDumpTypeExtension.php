<?php

namespace Symfonicat\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfonicat\Form\EventSubscriber\CoreYamlDumpSubscriber;

final class CoreYamlDumpTypeExtension extends AbstractTypeExtension
{
    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getExtendedTypes(): iterable
    {
        return [FormType::class];
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->addEventSubscriber(new CoreYamlDumpSubscriber($this->requestStack));
    }
}
