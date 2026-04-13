<?php

namespace Symfonicat\Service;

use Symfonicat\Repository\ModuleRepository;
use Symfony\Component\HttpFoundation\RequestStack;

final class ModuleService
{
    public function __construct (

        private readonly RequestStack $requestStack,
        private readonly PathService $pathService,
        private readonly ModuleRepository $moduleRepository

    ) {
        
    }

    public function load () : mixed
    {
        $arg0 = $this->pathService->arg(0);
        $arg1 = $this->pathService->arg(1);

        if ($arg0 !== 'm' || $arg1 === NULL) {
            return NULL;
        }

        return $this->moduleRepository->findOneBySlug($arg1);
    }
}
