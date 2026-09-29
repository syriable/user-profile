<?php

declare(strict_types=1);
use Syriable\UserProfile\Contracts\LanguageSearchResolver;
use Syriable\UserProfile\Contracts\SkillSearchResolver;
use Syriable\UserProfile\Exceptions\UserProfileException;
use Syriable\UserProfile\Search\DatabaseLanguageSearchResolver;
use Syriable\UserProfile\Search\DatabaseSkillSearchResolver;
use Syriable\UserProfile\Search\LanguageSearchCriteria;
use Syriable\UserProfile\Search\SkillSearchCriteria;

arch('it will not use debugging functions')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->each->not->toBeUsed();

arch('source files declare strict types')
    ->expect('Syriable\UserProfile')
    ->toUseStrictTypes();

arch('exceptions implement the package marker interface')
    ->expect('Syriable\UserProfile\Exceptions')
    ->classes()
    ->toImplement(UserProfileException::class);

arch('events are immutable')
    ->expect('Syriable\UserProfile\Events')
    ->toBeReadonly()
    ->toBeFinal();

arch('database resolvers implement their contracts')
    ->expect(DatabaseSkillSearchResolver::class)
    ->toImplement(SkillSearchResolver::class)
    ->and(DatabaseLanguageSearchResolver::class)
    ->toImplement(LanguageSearchResolver::class);

arch('search criteria are immutable value objects')
    ->expect([
        SkillSearchCriteria::class,
        LanguageSearchCriteria::class,
    ])
    ->toBeReadonly()
    ->toBeFinal();
