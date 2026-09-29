<?php

declare(strict_types=1);

namespace Syriable\UserProfile\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Syriable\UserProfile\Concerns\BelongsToProfileOwner;
use Syriable\UserProfile\Concerns\ValidatesAttributes;
use Syriable\UserProfile\Database\Factories\AwardFactory;
use Syriable\UserProfile\Support\PackageConfig;

/**
 * An award, honour or achievement received by a user.
 *
 * @property int $id
 * @property int|string $user_id
 * @property string $title
 * @property string|null $issuer
 * @property Carbon|null $date_received
 * @property string|null $description
 * @property string|null $url
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Award extends Model
{
    use BelongsToProfileOwner;

    /** @use HasFactory<AwardFactory> */
    use HasFactory;

    use ValidatesAttributes;

    protected $fillable = [
        'title',
        'issuer',
        'date_received',
        'description',
        'url',
    ];

    public function getTable(): string
    {
        return PackageConfig::table('awards');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'issuer' => ['nullable', 'string', 'max:255'],
            'date_received' => ['nullable', 'date'],
            'description' => ['nullable', 'string'],
            'url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_received' => 'date',
        ];
    }

    protected static function newFactory(): AwardFactory
    {
        return AwardFactory::new();
    }
}
