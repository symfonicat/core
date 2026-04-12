<?php

namespace Symfonicat\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class BodyIndentExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('indent_body', $this->indentBody(...), ['is_safe' => ['html']]),
        ];
    }

    public function indentBody(string|Markup $html, int $indent = 16): Markup
    {
        $raw = $this->normalizeIndent((string) $html);
        $raw = trim($raw);
        if ($raw === '') {
            return new Markup('', 'UTF-8');
        }

        $pad = str_repeat(' ', $indent);
        $lines = explode("\n", $raw);
        $formatted = $pad . implode("\n" . $pad, $lines) . "\n";

        return new Markup($formatted, 'UTF-8');
    }

    private function normalizeIndent(string $html): string
    {
        $lines = preg_split('/\r?\n/', $html);
        if ($lines === false || $lines === []) {
            return $html;
        }

        $minIndent = null;
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            if (preg_match('/^( +)/', $line, $matches) === 1) {
                $indent = strlen($matches[1]);
                $minIndent = $minIndent === null ? $indent : min($minIndent, $indent);
            } else {
                $minIndent = 0;
                break;
            }
        }

        if ($minIndent === null || $minIndent === 0) {
            return $html;
        }

        $prefix = str_repeat(' ', $minIndent);
        $stripped = array_map(
            static fn (string $line): string => str_starts_with($line, $prefix)
                ? substr($line, $minIndent)
                : $line,
            $lines
        );

        return implode("\n", $stripped);
    }
}
