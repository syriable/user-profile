<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\BelongsToProfileOwner;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\CertificationFactory;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * A professional certification claimed by a profile owner.
 *
 * A credential ID or URL is stored as provided; the package never treats a
 * certification as verified.
 *
 * @property int $id
 * @property string $profileable_type
 * @property int|string $profileable_id
 * @property string $name
 * @property string $issuing_organization
 * @property Carbon|null $issue_date
 * @property Carbon|null $expiration_date Null means the certification does not expire.
 * @property string|null $credential_id
 * @property string|null $credential_url
 * @property string|null $description
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Certification extends Model
{
    use BelongsToProfileOwner;

    /** @use HasFactory<CertificationFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'name',
        'issuing_organization',
        'issue_date',
        'expiration_date',
        'credential_id',
        'credential_url',
        'description',
    ];

    public function getTable(): string
    {
        return PackageConfig::table('certifications');
    }

    public function expires(): bool
    {
        return $this->expiration_date !== null;
    }

    public function isExpired(?DateTimeInterface $at = null): bool
    {
        return $this->expiration_date !== null
            && $this->expiration_date->endOfDay()->lt($at ?? now());
    }

    /**
     * Certifications that have not expired (including those that never expire).
     *
     * @param  Builder<static>  $query
     */
    public function scopeValid(Builder $query, ?DateTimeInterface $at = null): void
    {
        $date = Carbon::instance($at ?? now())->toDateString();

        $query->where(fn (Builder $query) => $query
            ->whereNull('expiration_date')
            ->orWhereDate('expiration_date', '>=', $date));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeExpired(Builder $query, ?DateTimeInterface $at = null): void
    {
        $query->whereNotNull('expiration_date')
            ->whereDate('expiration_date', '<', Carbon::instance($at ?? now())->toDateString());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'issuing_organization' => ['required', 'string', 'max:255'],
            'issue_date' => ['nullable', 'date'],
            'expiration_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'credential_id' => ['nullable', 'string', 'max:255'],
            'credential_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'description' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'expiration_date' => 'date',
        ];
    }

    protected static function newFactory(): CertificationFactory
    {
        return CertificationFactory::new();
    }
}
