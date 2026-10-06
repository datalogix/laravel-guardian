<?php

namespace Datalogix\Guardian\Framework\Inertia;

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
