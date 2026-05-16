<?php

namespace App\Tests\Integration\Form;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Subdomain;
use Symfonicat\Entity\SubdomainEnv;
use Symfonicat\Form\ApplicationType;
use Symfonicat\Form\DomainType;
use Symfonicat\Form\SubdomainType;
use Symfony\Component\Form\FormFactoryInterface;

final class ScopedEnvFormTypeTest extends SymfonicatKernelTestCase
{
    public function testSubdomainFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $subdomain = $this->createSubdomain('subdomain1');
        $this->setSubdomainEnv($subdomain, $env, 'green');

        $subdomain = $this->entityManager()->getRepository(Subdomain::class)->find('core/subdomain1');
        self::assertInstanceOf(Subdomain::class, $subdomain);

        $view = $this->formFactory()
            ->create(SubdomainType::class, $subdomain, ['id_editable' => false])
            ->createView();

        self::assertSame('colors', $view['env'][0]['envParent']->vars['value']);
        self::assertSame('primary', $view['env'][0]['env']->vars['value']);
    }

    public function testDomainFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $domain = $this->createDomain('example.com');
        $this->setDomainEnv($domain, $env, 'blue');

        $domain = $this->entityManager()->getRepository(Domain::class)->find('example.com');
        self::assertInstanceOf(Domain::class, $domain);

        $view = $this->formFactory()
            ->create(DomainType::class, $domain)
            ->createView();

        self::assertSame('colors', $view['env'][0]['envParent']->vars['value']);
        self::assertSame('primary', $view['env'][0]['env']->vars['value']);
    }

    public function testProjectApplicationFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $application = (new Application())
            ->setId('core/test')
            ->setName('Test Application');
        $applicationEnv = (new ApplicationEnv())
            ->setEnv($env)
            ->setValue('red');

        $application->addEnv($applicationEnv);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($applicationEnv);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $application = $this->entityManager()->getRepository(Application::class)->find('core/test');
        self::assertInstanceOf(Application::class, $application);

        $view = $this->formFactory()
            ->create(ApplicationType::class, $application, ['id_editable' => false])
            ->createView();

        self::assertSame('colors', $view['env'][0]['envParent']->vars['value']);
        self::assertSame('primary', $view['env'][0]['env']->vars['value']);
    }

    public function testApplicationFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $domain = $this->createDomain('example.com');
        $subdomain = $this->createSubdomain('subdomain1', $domain);
        $application = $this->createApplication('Example Application', Application::TYPE_SUBDOMAIN, $domain, $subdomain);
        $this->setApplicationEnv($application, $env, 'purple');

        $application = $this->entityManager()->getRepository(Application::class)->find($application->getId());
        self::assertInstanceOf(Application::class, $application);

        $view = $this->formFactory()
            ->create(ApplicationType::class, $application)
            ->createView();

        self::assertSame('colors', $view['env'][0]['envParent']->vars['value']);
        self::assertSame('primary', $view['env'][0]['env']->vars['value']);
    }

    public function testEndpointApplicationFormRestoresSelectedEndpoint(): void
    {
        $endpoint = $this->createEndpoint('core/test');
        $application = $this->createApplication('Example Endpoint Application', Application::TYPE_ENDPOINT, null, null, $endpoint);

        $application = $this->entityManager()->getRepository(Application::class)->find($application->getId());
        self::assertInstanceOf(Application::class, $application);

        $view = $this->formFactory()
            ->create(ApplicationType::class, $application)
            ->createView();

        self::assertSame('core/test', $view['endpoint']->vars['value']);
    }

    private function formFactory(): FormFactoryInterface
    {
        return static::getContainer()->get(FormFactoryInterface::class);
    }
}
