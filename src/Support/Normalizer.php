<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Normalizer as IntlNormalizer;

/**
 * Produces the canonical, comparable form of names, aliases and search terms.
 *
 * The same function is applied when storing `normalized_*` columns and when
 * searching, which keeps matching case-insensitive and whitespace-insensitive
 * on every supported database without relying on collations.
 */
final class Normalizer
{
    public static function normalize(string $value): string
    {
        if (class_exists(IntlNormalizer::class)) {
            $normalized = IntlNormalizer::normalize($value, IntlNormalizer::FORM_KC);

            if (is_string($normalized)) {
                $value = $normalized;
            }
        }

        $value = (string) preg_replace('/\s+/u', ' ', $value);

        return mb_strtolower(trim($value));
    }

    /**
     * Escapes LIKE wildcards using "!" as the escape character, which needs no
     * special quoting on SQLite, MySQL, PostgreSQL or SQL Server.
     */
    public static function escapeLike(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }
}
