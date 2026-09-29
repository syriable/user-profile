<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

/**
 * Lightweight syntactic checks for language identifiers.
 */
final class LanguageTag
{
    /**
     * A BCP 47-style tag: a 2–3 letter primary subtag followed by optional
     * subtags, e.g. "en", "ar", "yue", "pt-BR", "zh-Hant", "sr-Latn-RS".
     */
    public const string BCP47 = '/^[A-Za-z]{2,3}(-[A-Za-z0-9]{1,8})*$/';

    public const string ISO_639_1 = '/^[a-z]{2}$/';

    public const string ISO_639_3 = '/^[a-z]{3}$/';

    public static function isValid(string $tag): bool
    {
        return preg_match(self::BCP47, $tag) === 1;
    }

    /**
     * Applies conventional BCP 47 casing so equivalent tags are stored
     * identically: "ZH-hant-tw" becomes "zh-Hant-TW".
     */
    public static function canonicalize(string $tag): string
    {
        $subtags = explode('-', str_replace('_', '-', trim($tag)));

        foreach ($subtags as $index => $subtag) {
            $subtags[$index] = match (true) {
                $index === 0 => strtolower($subtag),
                strlen($subtag) === 4 && ctype_alpha($subtag) => ucfirst(strtolower($subtag)),
                strlen($subtag) === 2 && ctype_alpha($subtag) => strtoupper($subtag),
                default => strtolower($subtag),
            };
        }

        return implode('-', $subtags);
    }
}
