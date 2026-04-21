<?php

namespace Symfonicat\Twig;

use Symfonicat\Service\ApplicationService;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class ApplicationExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(
        private readonly ApplicationService $applicationService,
        private readonly RequestStack $requestStack,
    ) {
    }
    
    public function getFunctions(): array
    {
        return [
            new TwigFunction('applicationHelper', $this->applicationHelper(...), ['is_safe' => ['html']]),
        ];
    }

    public function getGlobals(): array
    {
        if (null === $this->requestStack->getCurrentRequest()) {
            return [
                'application' => null,
            ];
        }

        $application = $this->applicationService->load();

        return [
            'application' => $application,
        ];
    }

    private function applicationHelper() {
        
        $application = $this->applicationService->load();
        $helper = '';

        if ($application) {

            $id = $application->getId();
            $path = $this->requestStack->getCurrentRequest()->getPathInfo();
            $helper = <<<SCRIPT
<script type="text/javascript">
    window.symfonicatApplication = {
        id: '$id',
        path: '$path',
    }
</script>
SCRIPT;

        }

        return $helper;
    }
}
