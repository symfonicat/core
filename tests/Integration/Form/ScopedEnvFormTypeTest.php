<?php

namespace App\Tests\Integration\Form;

use App\Tests\Support\SymfonicatKernelTestCase;
use Symfonicat\Entity\Application;
use Symfonicat\Entity\ApplicationEnv;
use Symfonicat\Entity\Domain;
use Symfonicat\Entity\DomainEnv;
use Symfonicat\Entity\Env;
use Symfonicat\Entity\Project;
use Symfonicat\Entity\ProjectEnv;
use Symfonicat\Form\ApplicationType;
use Symfonicat\Form\DomainType;
use Symfonicat\Form\ProjectType;
use Symfony\Component\Form\FormFactoryInterface;

final class ScopedEnvFormTypeTest extends SymfonicatKernelTestCase
{
    public function testProjectFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $project = $this->createProject('project1', 'Project 1');
        $this->setProjectEnv($project, $env, 'green');

        $project = $this->entityManager()->getRepository(Project::class)->find('project1');
        self::assertInstanceOf(Project::class, $project);

        $view = $this->formFactory()
            ->create(ProjectType::class, $project, ['id_editable' => false])
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

    public function testApplicationFormRestoresSelectedEnvParent(): void
    {
        $env = $this->createEnv('primary', 'colors');
        $application = (new Application())
            ->setId('test');
        $applicationEnv = (new ApplicationEnv())
            ->setEnv($env)
            ->setValue('red');

        $application->addEnv($applicationEnv);

        $this->entityManager()->persist($application);
        $this->entityManager()->persist($applicationEnv);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        $application = $this->entityManager()->getRepository(Application::class)->find('test');
        self::assertInstanceOf(Application::class, $application);

        $view = $this->formFactory()
            ->create(ApplicationType::class, $application, ['id_editable' => false])
            ->createView();

        self::assertSame('colors', $view['env'][0]['envParent']->vars['value']);
        self::assertSame('primary', $view['env'][0]['env']->vars['value']);
    }

    private function formFactory(): FormFactoryInterface
    {
        return static::getContainer()->get(FormFactoryInterface::class);
    }
}
