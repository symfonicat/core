<?php

namespace Symfonicat\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\ProjectService;

abstract class AbstractModuleController extends AbstractController
{

    private bool $shouldRun = FALSE;

    public function __construct(

        public readonly DomainService $domainService,
        public readonly ModuleService $moduleService,
        public readonly ProjectService $projectService,
        public readonly PathService $pathService,

    ) {

        if (

            ($project = $this->projectService->load()) &&
            ($module = $this->moduleService->load()) &&
            $project->getModules()->contains($module)

        ) {

            $this->shouldRun = TRUE;

        }

        if (

            ($domain = $this->domainService->load()) &&
            ($module = $this->moduleService->load()) &&
            (!$project) &&
            $domain->getModules()->contains($module)

        ) {

            $this->shouldRun = TRUE;

        }

    }

    protected function module (
        
        Response $shouldRunResponse,
        $shouldNotRunResponse = FALSE

    ) : Response {

        if ($this->shouldRun) {

            return $shouldRunResponse;

        }

        else {

            if ($shouldNotRunResponse !== FALSE) {
                return $shouldNotRunResponse;
            }

            throw $this->createNotFoundException();

        }
    }
}
