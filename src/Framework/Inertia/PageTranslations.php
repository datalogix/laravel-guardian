<?php

namespace Datalogix\Guardian\Framework\Inertia;

/**
 * The lines of the bundled pages in the language of the request, keyed by their
 * English text, for the t() helper of the pages.
 *
 * The lines are the ones the pages show (resources/lines.json), translated with
 * __(), so an application that translates them in its own lang files, or adds a
 * language, is followed too. A line that stays the same is left out, since the
 * page shows the English text when it has no translation.
 */
class PageTranslations
{
    /**
     * @var array<int, string>|null
     */
    protected static ?array $lines = null;

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        $translations = [];

        foreach (static::lines() as $line) {
            $translation = __($line);

            if (is_string($translation) && $translation !== $line) {
                $translations[$line] = $translation;
            }
        }

        return $translations;
    }

    /**
     * @return array<int, string>
     */
    protected static function lines(): array
    {
        return static::$lines ??= json_decode(file_get_contents(__DIR__.'/resources/lines.json'), true);
    }
}
