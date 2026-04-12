<?php

namespace Symfonicat\Service;

use Symfonicat\Repository\RoutingRuleRepository;

final class RoutingRuleService
{
    public function __construct(
        private readonly RoutingRuleRepository $routingRuleRepository,
    ) {
    }

    public function loadDomainRules(): array
    {
        static $rules = FALSE;

        if ($rules !== FALSE) {
            return $rules;
        }

        $rules = $this->routingRuleRepository->findAllDomainRules();

        return $rules;
    }
}
