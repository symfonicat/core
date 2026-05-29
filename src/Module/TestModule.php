<?php

namespace App\Module;

use Symfonicat\Controller\AbstractModuleController;
use Symfonicat\Attribute\Module;
use Symfonicat\Attribute\ModuleRoute;
use Symfony\Component\HttpFoundation\Response;

#[ModuleRoute]
final class TestModule extends AbstractModuleController
{
    #[Module]
    public function test(): Response
    {

        return new Response('test');
    }
}
