<?php

declare(strict_types=1);

use Syriable\UserProfile\Support\Normalizer;

it('lowercases, trims and collapses whitespace', function (string $input, string $expected): void {
    expect(Normalizer::normalize($input))->toBe($expected);
})->with([
    ['JavaScript', 'javascript'],
    ['  Project    Management ', 'project management'],
    ["UI\tDesign\n", 'ui design'],
    ['ÉCOLE', 'école'],
    ['Ｌａｒａｖｅｌ', 'laravel'], // full-width characters (NFKC)
    ['', ''],
]);

it('escapes LIKE wildcards with the portable escape character', function (): void {
    expect(Normalizer::escapeLike('100%_done!'))->toBe('100!%!_done!!');
});
