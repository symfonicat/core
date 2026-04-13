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
            new TwigFilter('indent_links', $this->indentLinks(...), ['is_safe' => ['html']]),
            new TwigFilter('indent_scripts', $this->indentScripts(...), ['is_safe' => ['html']]),
            new TwigFilter('indent_json', $this->indentJson(...), ['is_safe' => ['html']]),
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

    public function indentLinks(string|Markup $html, int $indent = 16): Markup
    {
        return $this->indentTags((string) $html, '/<link\b[^>]*>/i', $indent);
    }

    public function indentScripts(string|Markup $html, int $indent = 16): Markup
    {
        return $this->indentTags((string) $html, '/<script\b[^>]*>.*?<\/script>/is', $indent);
    }

    public function indentJson(string|Markup $json, int $indent = 16): Markup
    {
        $raw = trim((string) $json);
        if ($raw === '') {
            return new Markup('', 'UTF-8');
        }

        $lines = preg_split('/\r?\n/', $raw);
        if ($lines === false || $lines === []) {
            return new Markup($raw, 'UTF-8');
        }

        $firstLine = array_shift($lines);
        if ($firstLine === null || $lines === []) {
            return new Markup($raw, 'UTF-8');
        }

        $pad = str_repeat(' ', $indent);
        $formatted = $firstLine."\n".$pad.implode("\n".$pad, $lines);

        return new Markup($formatted, 'UTF-8');
    }

    private function indentTags(string $html, string $pattern, int $indent): Markup
    {
        $result = preg_match_all($pattern, $html, $matches);
        if ($result === false) {
            return new Markup('', 'UTF-8');
        }

        $tags = array_map(static fn (string $tag): string => trim($tag), $matches[0] ?? []);
        $tags = array_values(array_filter($tags, static fn (string $tag): bool => $tag !== ''));
        if ($tags === []) {
            return new Markup('', 'UTF-8');
        }

        $pad = str_repeat(' ', $indent);
        $formatted = $pad . implode("\n" . $pad, $tags) . "\n";

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
