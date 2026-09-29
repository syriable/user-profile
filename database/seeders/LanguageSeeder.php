<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Seeders;

use Illuminate\Database\Seeder;
use Syriable\UserProfile\Models\Language;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Seeds a starter catalog of widely spoken languages. Safe to run repeatedly:
 * records are matched on their `code`. Applications can add any language the
 * catalog does not include.
 */
class LanguageSeeder extends Seeder
{
    /**
     * name, native name, BCP 47 code, ISO 639-1, ISO 639-3
     *
     * @var list<array{string, string, string, string|null, string}>
     */
    protected array $languages = [
        ['Amharic', 'አማርኛ', 'am', 'am', 'amh'],
        ['Arabic', 'العربية', 'ar', 'ar', 'ara'],
        ['Armenian', 'Հայերեն', 'hy', 'hy', 'hye'],
        ['Bengali', 'বাংলা', 'bn', 'bn', 'ben'],
        ['Cantonese', '粵語', 'yue', null, 'yue'],
        ['Chinese', '中文', 'zh', 'zh', 'zho'],
        ['Czech', 'Čeština', 'cs', 'cs', 'ces'],
        ['Danish', 'Dansk', 'da', 'da', 'dan'],
        ['Dutch', 'Nederlands', 'nl', 'nl', 'nld'],
        ['English', 'English', 'en', 'en', 'eng'],
        ['Finnish', 'Suomi', 'fi', 'fi', 'fin'],
        ['French', 'Français', 'fr', 'fr', 'fra'],
        ['German', 'Deutsch', 'de', 'de', 'deu'],
        ['Greek', 'Ελληνικά', 'el', 'el', 'ell'],
        ['Hebrew', 'עברית', 'he', 'he', 'heb'],
        ['Hindi', 'हिन्दी', 'hi', 'hi', 'hin'],
        ['Hungarian', 'Magyar', 'hu', 'hu', 'hun'],
        ['Indonesian', 'Bahasa Indonesia', 'id', 'id', 'ind'],
        ['Italian', 'Italiano', 'it', 'it', 'ita'],
        ['Japanese', '日本語', 'ja', 'ja', 'jpn'],
        ['Korean', '한국어', 'ko', 'ko', 'kor'],
        ['Kurdish', 'Kurdî', 'ku', 'ku', 'kur'],
        ['Malay', 'Bahasa Melayu', 'ms', 'ms', 'msa'],
        ['Norwegian', 'Norsk', 'no', 'no', 'nor'],
        ['Persian', 'فارسی', 'fa', 'fa', 'fas'],
        ['Polish', 'Polski', 'pl', 'pl', 'pol'],
        ['Portuguese', 'Português', 'pt', 'pt', 'por'],
        ['Romanian', 'Română', 'ro', 'ro', 'ron'],
        ['Russian', 'Русский', 'ru', 'ru', 'rus'],
        ['Spanish', 'Español', 'es', 'es', 'spa'],
        ['Swahili', 'Kiswahili', 'sw', 'sw', 'swa'],
        ['Swedish', 'Svenska', 'sv', 'sv', 'swe'],
        ['Tagalog', 'Tagalog', 'tl', 'tl', 'tgl'],
        ['Thai', 'ไทย', 'th', 'th', 'tha'],
        ['Turkish', 'Türkçe', 'tr', 'tr', 'tur'],
        ['Ukrainian', 'Українська', 'uk', 'uk', 'ukr'],
        ['Urdu', 'اردو', 'ur', 'ur', 'urd'],
        ['Vietnamese', 'Tiếng Việt', 'vi', 'vi', 'vie'],
    ];

    public function run(): void
    {
        $model = PackageConfig::model('language', Language::class);

        foreach ($this->languages as [$name, $nativeName, $code, $iso6391, $iso6393]) {
            $model::query()->updateOrCreate(['code' => $code], [
                'name' => $name,
                'native_name' => $nativeName,
                'iso_639_1' => $iso6391,
                'iso_639_3' => $iso6393,
            ]);
        }
    }
}
