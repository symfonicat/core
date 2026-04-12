<?php

namespace Symfonicat\Service;

use Symfonicat\Service\DomainService;
use Symfonicat\Service\SubdomainService;
use Symfonicat\Repository\ProjectRepository;
use Pdp\Domain;
use Pdp\Rules;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

final class ProjectService
{

    public function __construct (

        private readonly DomainService $domainService,
        private readonly SubdomainService $subdomainService,
        private readonly ProjectRepository $projectRepository,

    ) {
    }

    public function load () {
        $slug = $this->subdomainService->getSubdomainByIndex(0);
        $domain = $this->domainService->load();

        if ($slug === NULL || $slug === '') {
            return null;
        }

        if ($domain) {
            return $this->projectRepository->findOneBySlugForDomain($slug, $domain->getId());
        }

        return $this->projectRepository->findOneBySlug($slug);

    }
}
