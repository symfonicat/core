<?php

namespace Symfonicat\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Markup;
use Twig\TwigFilter;

final class MessageExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('message', $this->formatMessage(...), ['is_safe' => ['html']]),
        ];
    }

    public function formatMessage(?string $message): Markup
    {
        $raw = (string) $message;
        if ($raw === '') {
            return new Markup('', 'UTF-8');
        }

        $parts = preg_split('/(`[^`]*`)/', $raw, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || $parts === []) {
            return new Markup(htmlspecialchars($raw, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'UTF-8');
        }

        $formatted = '';
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (str_starts_with($part, '`') && str_ends_with($part, '`') && strlen($part) >= 2) {
                $code = substr($part, 1, -1);
                $formatted .= '<code>' . htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
                continue;
            }

            $formatted .= htmlspecialchars($part, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        return new Markup($formatted, 'UTF-8');
    }
}
