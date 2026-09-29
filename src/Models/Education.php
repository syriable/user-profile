<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\BelongsToProfileOwner;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\EducationFactory;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * One entry in a profile owner's education history.
 *
 * Source of truth for completion: `end_date` when the exact date is known.
 * `graduation_year` is derived from `end_date` whenever `end_date` is set, and
 * can be stored on its own when only the year is known. For ongoing studies
 * (`is_current`), `end_date` must be empty and `graduation_year` is the
 * optional expected year.
 *
 * @property int $id
 * @property string $profileable_type
 * @property int|string $profileable_id
 * @property string|null $type Application-defined taxonomy, e.g. "university", "bootcamp".
 * @property string $institution_name
 * @property string|null $degree
 * @property string|null $field_of_study
 * @property string|null $country_code ISO 3166-1 alpha-2.
 * @property string|null $city
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null $graduation_year
 * @property bool $is_current
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Education extends Model
{
    use BelongsToProfileOwner;

    /** @use HasFactory<EducationFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'type',
        'institution_name',
        'degree',
        'field_of_study',
        'country_code',
        'city',
        'start_date',
        'end_date',
        'graduation_year',
        'is_current',
        'description',
    ];

    protected $attributes = [
        'is_current' => false,
    ];

    public function getTable(): string
    {
        return PackageConfig::table('educations');
    }

    /**
     * Current studies first, then most recently finished.
     *
     * @param  Builder<static>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('is_current')
            ->orderByRaw('case when graduation_year is null then 1 else 0 end')
            ->orderByDesc('graduation_year')
            ->orderByDesc('start_date')
            ->orderByDesc($this->getKeyName());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxYear = (int) now()->year + 15;

        return [
            'type' => ['nullable', 'string', 'max:100'],
            'institution_name' => ['required', 'string', 'max:255'],
            'degree' => ['nullable', 'string', 'max:255'],
            'field_of_study' => ['nullable', 'string', 'max:255'],
            'country_code' => ['nullable', 'string', 'regex:/^[A-Z]{2}$/'],
            'city' => ['nullable', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => [
                'nullable',
                'date',
                'after_or_equal:start_date',
                'prohibited_if_accepted:is_current',
            ],
            'graduation_year' => [
                'nullable',
                'integer',
                'between:1900,'.$maxYear,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_numeric($value) && $this->start_date !== null && (int) $value < $this->start_date->year) {
                        $fail('The graduation year cannot be before the start date.');
                    }
                },
            ],
            'is_current' => ['boolean'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'graduation_year' => 'integer',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string|null, string|null>
     */
    protected function countryCode(): Attribute
    {
        return Attribute::make(set: fn (?string $value): ?string => $value === null ? null : strtoupper(trim($value)));
    }

    protected function prepareAttributes(): void
    {
        if ($this->end_date !== null) {
            $this->graduation_year = $this->end_date->year;
        }
    }

    protected static function newFactory(): EducationFactory
    {
        return EducationFactory::new();
    }
}
