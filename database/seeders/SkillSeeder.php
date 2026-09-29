<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Database\Seeders;

use Illuminate\Database\Seeder;
use Syriable\UserProfile\Models\Skill;
use Syriable\UserProfile\Models\SkillCategory;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * Seeds a small starter skill catalog with categories and aliases. Safe to run
 * repeatedly: categories and skills are matched on their name.
 */
class SkillSeeder extends Seeder
{
    /**
     * category => [skill name => aliases]
     *
     * @var array<string, array<string, list<string>>>
     */
    protected array $skills = [
        'Programming Languages' => [
            'JavaScript' => ['JS', 'ECMAScript'],
            'TypeScript' => ['TS'],
            'PHP' => ['PHP Hypertext Preprocessor'],
            'Python' => ['py'],
            'Java' => [],
            'C' => [],
            'C++' => ['cpp'],
            'C#' => ['C Sharp', 'csharp'],
            'SQL' => ['Structured Query Language'],
        ],
        'Frameworks & Tools' => [
            'Laravel' => [],
            'React' => ['React.js', 'ReactJS'],
            'Vue.js' => ['Vue', 'VueJS'],
            'Git' => [],
        ],
        'Design' => [
            'Graphic Design' => [],
            'User Interface Design' => ['UI Design', 'UI'],
            'User Experience Design' => ['UX Design', 'UX'],
        ],
        'Business' => [
            'Project Management' => ['PM'],
            'Search Engine Optimization' => ['SEO'],
        ],
    ];

    public function run(): void
    {
        $categoryModel = PackageConfig::model('skill_category', SkillCategory::class);
        $skillModel = PackageConfig::model('skill', Skill::class);

        foreach ($this->skills as $categoryName => $skills) {
            $category = $categoryModel::query()->firstOrCreate(['name' => $categoryName]);

            foreach ($skills as $name => $aliases) {
                $skill = $skillModel::query()->firstOrCreate(['name' => $name], ['category_id' => $category->id]);

                foreach ($aliases as $alias) {
                    $skill->addAlias($alias);
                }
            }
        }
    }
}
