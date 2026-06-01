<?php

namespace App\Tests\Unit\Form\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Symfonicat\Form\EventSubscriber\CoreYamlDumpSubscriber;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Forms;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class CoreYamlDumpSubscriberTest extends TestCase
{
    public function testMarksCoreRequestsWhenRootFormIsSubmitted(): void
    {
        $request = Request::create('/core/a/create', 'POST');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $builder = Forms::createFormFactory()
            ->createBuilder(FormType::class, null, ['csrf_protection' => false]);
        $builder->addEventSubscriber(new CoreYamlDumpSubscriber($requestStack));

        $builder->getForm()->submit([]);

        self::assertTrue($request->attributes->getBoolean(CoreYamlDumpSubscriber::REQUEST_ATTRIBUTE));
    }

    public function testSkipsNonCoreRequests(): void
    {
        $request = Request::create('/docs', 'POST');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        $builder = Forms::createFormFactory()
            ->createBuilder(FormType::class, null, ['csrf_protection' => false]);
        $builder->addEventSubscriber(new CoreYamlDumpSubscriber($requestStack));

        $builder->getForm()->submit([]);

        self::assertFalse($request->attributes->getBoolean(CoreYamlDumpSubscriber::REQUEST_ATTRIBUTE));
    }
}
