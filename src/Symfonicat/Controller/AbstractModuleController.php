<?php

namespace Symfonicat\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfonicat\Service\ApplicationService;
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
        public readonly ?ApplicationService $applicationService = null,

    ) {

        $module = $this->moduleService->load();

        if (!$module) {
            return;
        }

        $project = $this->projectService->load();

        if ($project && $project->hasModule($module)) {
            $this->shouldRun = TRUE;

            return;
        }

        $application = $this->applicationService?->load();

        if ($application && $application->hasModule($module)) {
            $this->shouldRun = TRUE;

            return;
        }

        if (!$project && ($domain = $this->domainService->load()) && $domain->hasModule($module)) {
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
