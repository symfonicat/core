<?php

namespace Symfonicat\Twig;

use Symfonicat\Entity\EnvParent;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

final class EnvExtension extends AbstractExtension implements GlobalsInterface
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('env', $this->render(...)),
            new TwigFunction('env_json', $this->renderJson(...), ['is_safe' => ['html']]),
            new TwigFunction('env_helper', $this->renderHelper(...), ['is_safe' => ['html']]),
            new TwigFunction('env_parent_entries', $this->envParentEntries(...)),
        ];
    }

    public function getGlobals(): array
    {
        return [
            'env' => [],
        ];
    }

    public function render(string $id): ?string
    {
        return null;
    }

    public function renderJson(): string
    {
        return '{}';
    }

    public function renderHelper(): string
    {
        return '{}';
    }

    /**
     * @param iterable<object> $rows
     * @return list<string>
     */
    public function envParentEntries(iterable $rows, EnvParent $envParent): array
    {
        $entries = [];

        foreach ($rows as $row) {
            if (!method_exists($row, 'getEnv') || !method_exists($row, 'getValue')) {
                continue;
            }

            $env = $row->getEnv();
            if ($env === null || !method_exists($env, 'getId') || !method_exists($env, 'getEnvParent')) {
                continue;
            }

            $rowEnvParent = $env->getEnvParent();
            if ($rowEnvParent?->getId() !== $envParent->getId()) {
                continue;
            }

            $envId = trim((string) $env->getId());
            if ($envId === '') {
                continue;
            }

            $entries[] = sprintf('%s: %s', $envId, (string) $row->getValue());
        }

        return $entries;
    }
}
