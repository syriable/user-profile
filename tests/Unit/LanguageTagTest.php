<?php

declare(strict_types=1);

use Syriable\UserProfile\Support\LanguageTag;

it('validates language tags', function (string $tag, bool $valid): void {
    expect(LanguageTag::isValid($tag))->toBe($valid);
})->with([
    ['en', true],
    ['yue', true],
    ['pt-BR', true],
    ['sr-Latn-RS', true],
    ['e', false],
    ['english', false],
    ['en US', false],
    ['', false],
]);

it('applies conventional casing', function (string $tag, string $expected): void {
    expect(LanguageTag::canonicalize($tag))->toBe($expected);
})->with([
    ['EN', 'en'],
    ['pt-br', 'pt-BR'],
    ['ZH-hant-tw', 'zh-Hant-TW'],
    ['en_GB', 'en-GB'],
    ['es-419', 'es-419'],
]);
