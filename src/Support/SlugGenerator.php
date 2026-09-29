<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Generates unique slugs while keeping symbols that distinguish technology
 * names ("C", "C++", "C#") from collapsing into the same slug.
 *
 * @internal
 */
final class SlugGenerator
{
    public static function unique(Model $model, string $name, string $column = 'slug'): string
    {
        $base = Str::slug(str_replace(['+', '#'], [' plus ', ' sharp '], $name));

        if ($base === '') {
            $base = Str::slug(class_basename($model));
        }

        $slug = $base;
        $suffix = 2;

        while ($model->newQuery()
            ->where($column, $slug)
            ->when($model->exists, fn ($query) => $query->whereKeyNot($model->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
