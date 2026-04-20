<?php

namespace App\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfonicat\Twig\BodyIndentExtension;
use Twig\Markup;

/**
 * Pure unit coverage of BodyIndentExtension — the Twig filter family that
 * keeps Symfonicat's templated HTML output neatly indented at arbitrary
 * column offsets. We pin the most important user-observable contracts here
 * because the filters run on every public response.
 */
final class BodyIndentExtensionTest extends TestCase
{
    private BodyIndentExtension $extension;

    protected function setUp(): void
    {
        parent::setUp();

        if (!extension_loaded('tidy')) {
            self::markTestSkipped('indent_body requires the ext-tidy PHP extension.');
        }

        $this->extension = new BodyIndentExtension();
    }

    public function testEmptyBodyProducesEmptyMarkup(): void
    {
        $output = $this->extension->indentBody('   ');

        self::assertInstanceOf(Markup::class, $output);
        self::assertSame('', (string) $output);
    }

    public function testIndentBodyPadsContinuationLinesByRequestedAmount(): void
    {
        $html = <<<HTML
<div>
    <span>hi</span>
</div>
HTML;

        $output = (string) $this->extension->indentBody($html, 4);

        $lines = preg_split('/\r?\n/', rtrim($output, "\n"));

        self::assertIsArray($lines);
        self::assertSame('<div>', $lines[0], 'first line must never be indented so the caller can place the block inline');

        foreach (array_slice($lines, 1) as $index => $line) {
            if ($line === '') {
                continue;
            }
            self::assertStringStartsWith(
                '    ',
                $line,
                sprintf('continuation line %d should start with the requested 4-space pad', $index + 1),
            );
        }
    }

    public function testIndentLinksRendersOneTagPerLineAtRequestedIndent(): void
    {
        $html = '<link rel="stylesheet" href="/a.css"><link rel="stylesheet" href="/b.css">';

        $output = (string) $this->extension->indentLinks($html, 8);
        $lines = preg_split('/\r?\n/', rtrim($output, "\n"));

        self::assertIsArray($lines);
        self::assertCount(2, $lines, 'each <link> tag must land on its own line');

        foreach ($lines as $line) {
            self::assertStringStartsWith(str_repeat(' ', 8), $line);
            self::assertStringContainsString('<link', $line);
        }
    }

    public function testIndentScriptsMatchesInlineAndExternalScriptTags(): void
    {
        $html = '<script src="/a.js"></script>INBETWEEN<script>console.log(1)</script>';

        $output = (string) $this->extension->indentScripts($html, 4);
        $lines = preg_split('/\r?\n/', rtrim($output, "\n"));

        self::assertIsArray($lines);
        self::assertCount(2, $lines);
        self::assertStringContainsString('<script src="/a.js">', $lines[0]);
        self::assertStringContainsString('console.log(1)', $lines[1]);
    }

    public function testIndentJsonKeepsFirstLineFlushAndPadsRemainderUniformly(): void
    {
        $json = <<<JSON
{
    "hello": "world",
    "nested": {
        "key": "value"
    }
}
JSON;

        $output = (string) $this->extension->indentJson($json, 6);

        $lines = preg_split('/\r?\n/', $output);

        self::assertIsArray($lines);
        self::assertSame('{', $lines[0]);
        self::assertStringStartsWith('      ', $lines[1]);
        self::assertStringContainsString('"hello": "world"', $lines[1]);
    }

    public function testIndentJsonReturnsInputUnchangedForSingleLineJson(): void
    {
        $json = '{"hello":"world"}';

        $output = (string) $this->extension->indentJson($json, 12);

        self::assertSame($json, $output);
    }

    public function testIndentTagsReturnsEmptyWhenNoMatches(): void
    {
        $output = (string) $this->extension->indentLinks('<div>no links here</div>', 8);

        self::assertSame('', $output);
    }

    public function testIndentBodyNormalizesPreExistingIndentBeforeReindenting(): void
    {
        // Four-space indented block simulating what a Twig `set` capture yields.
        $html = "    <div>\n        <span>hi</span>\n    </div>";

        $output = (string) $this->extension->indentBody($html, 2);
        $lines = preg_split('/\r?\n/', rtrim($output, "\n"));

        self::assertIsArray($lines);
        self::assertSame('<div>', $lines[0]);

        // The common 4-space prefix is stripped before re-indenting to 2 spaces,
        // so the original 8-space inner line becomes exactly 4 extra spaces on top
        // of the requested 2-space pad.
        self::assertStringStartsWith('  ', $lines[1]);
        self::assertStringNotContainsString('        ', $lines[1]);
    }
}
