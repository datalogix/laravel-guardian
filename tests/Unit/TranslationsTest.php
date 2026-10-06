<?php

namespace Datalogix\Guardian\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class TranslationsTest extends TestCase
{
    protected const LANG = __DIR__.'/../../resources/lang';

    protected const PAGES = __DIR__.'/../../src/Framework/Inertia/resources/js';

    public static function languages(): array
    {
        return array_map(fn (string $path) => [basename($path, '.json')], glob(self::LANG.'/*.json'));
    }

    protected function lines(string $language): array
    {
        return json_decode(file_get_contents(self::LANG."/{$language}.json"), true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * The lines the Inertia pages pass to t(), for both stacks.
     */
    protected static function pageLines(): array
    {
        $lines = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::PAGES)) as $file) {
            if (! preg_match('/\.(vue|tsx|ts)$/', $file->getFilename())) {
                continue;
            }

            // Comments hold examples, not lines of the page.
            $source = preg_replace(['#/\*.*?\*/#s', '#^\s*//.*$#m'], '', file_get_contents($file->getPathname()));

            preg_match_all('/\bt\((?:\'((?:[^\'\\\\]|\\\\.)*)\'|"((?:[^"\\\\]|\\\\.)*)")/', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $lines[] = stripslashes($match[2] ?? '' ?: $match[1]);
            }
        }

        // The labels of the login identifier reach t() through identifier.ts.
        foreach (glob(self::PAGES.'/*/components/identifier.ts') as $file) {
            preg_match_all("/label: '([^']+)'/", file_get_contents($file), $matches);
            array_push($lines, ...$matches[1]);
        }

        $lines = array_values(array_unique($lines));
        sort($lines);

        return $lines;
    }

    #[DataProvider('languages')]
    public function test_every_line_of_the_pages_is_translated(string $language): void
    {
        $missing = array_values(array_diff(static::pageLines(), array_keys($this->lines($language))));

        $this->assertSame([], $missing, "Lines of the Inertia pages without a [{$language}] translation.");
    }

    #[DataProvider('languages')]
    public function test_every_translation_keeps_the_placeholders_of_its_line(string $language): void
    {
        $broken = [];

        foreach ($this->lines($language) as $line => $translation) {
            preg_match_all('/:[a-z_]+/', $line, $placeholders);

            foreach ($placeholders[0] as $placeholder) {
                if (! str_contains($translation, $placeholder)) {
                    $broken[] = "{$line} ({$placeholder})";
                }
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_the_server_sends_exactly_the_lines_the_pages_show(): void
    {
        $sent = json_decode(file_get_contents(__DIR__.'/../../src/Framework/Inertia/resources/lines.json'), true);
        sort($sent);

        $this->assertSame(static::pageLines(), $sent, 'Update src/Framework/Inertia/resources/lines.json with the lines of the pages.');
    }

    public function test_the_pages_are_found(): void
    {
        // Guards the scan above against a path that no longer matches anything.
        $this->assertContains('Sign in', static::pageLines());
        $this->assertContains('Remember this device for :days days', static::pageLines());
    }
}
