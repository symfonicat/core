<?php

namespace Symfonicat\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfonicat\Entity\Endpoint;
use Symfonicat\Service\DomainService;
use Symfonicat\Service\ModuleService;
use Symfonicat\Service\PathService;
use Symfonicat\Service\SubdomainService;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class AbstractModuleController extends AbstractController
{

    private bool $shouldRun = FALSE;

    public function __construct(

        public readonly DomainService $domainService,
        public readonly ModuleService $moduleService,
        public readonly SubdomainService $subdomainService,
        public readonly PathService $pathService,
        public readonly ?RequestStack $requestStack = null,

    ) {

        $module = $this->moduleService->load();

        if (!$module) {
            return;
        }

        $endpoint = $this->requestStack?->getCurrentRequest()?->attributes->get('endpoint');
        if ($endpoint instanceof Endpoint && $endpoint->hasModule($module)) {
            $this->shouldRun = TRUE;

            return;
        }

        $subdomain = $this->subdomainService->load();

        if ($subdomain && $subdomain->hasModule($module)) {
            $this->shouldRun = TRUE;

            return;
        }

        if (!$subdomain && ($domain = $this->domainService->load()) && $domain->hasModule($module)) {
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
