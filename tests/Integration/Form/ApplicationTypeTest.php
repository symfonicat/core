<?php

namespace App\Tests\Integration\Form;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Form\ApplicationType;
use Symfony\Component\Form\FormFactoryInterface;

final class ApplicationTypeTest extends SymfonicatKernelTestCase
{
    public function testApplicationFormAlwaysExposesDomainSubdomainAndEndpointWithoutType(): void
    {
        $view = $this->formFactory()
            ->create(ApplicationType::class, new Application())
            ->createView();

        self::assertArrayHasKey('domain', $view);
        self::assertArrayHasKey('subdomain', $view);
        self::assertArrayHasKey('endpoint', $view);
        self::assertArrayNotHasKey('type', $view);
        self::assertTrue($view['domain']->vars['required']);
        self::assertFalse($view['subdomain']->vars['required']);
        self::assertFalse($view['endpoint']->vars['required']);
    }

    private function formFactory(): FormFactoryInterface
    {
        return self::getContainer()->get(FormFactoryInterface::class);
    }
}
