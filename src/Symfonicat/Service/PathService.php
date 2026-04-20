<?php

namespace Symfonicat\Service;

use Symfony\Component\HttpFoundation\RequestStack;

final class PathService
{
    public function __construct (

        private readonly RequestStack $requestStack

    ) {
        
    }

    public function arg (int $index) : string | NULL
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === NULL) {
            return NULL;
        }

        $path = trim($request->getPathInfo(), '/');
        if ($path === '') {
            return NULL;
        }

        $parts = explode('/', $path);

        return $parts[$index] ?? NULL;
    }
}
