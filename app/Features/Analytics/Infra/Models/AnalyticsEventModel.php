<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Models;

use Database\Factories\AnalyticsEventModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for `analytics_events` (database.md:486-505). The raw
 * event log is append-only — `created_at` only, no `updated_at`; the
 * `ip_address` column holds the keyed HMAC-SHA256 digest (B4), never
 * the raw client IP.
 *
 * @property int $id
 * @property string $event_type
 * @property string $session_id
 * @property string|null $page_type
 * @property int|null $page_entity_id
 * @property string|null $page_url
 * @property array<string, mixed>|null $metadata
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property \Illuminate\Support\Carbon $created_at
 */
final class AnalyticsEventModel extends Model
{
    use HasFactory;

    protected $table = 'analytics_events';

    public const UPDATED_AT = null;

    protected $fillable = [
        'event_type',
        'session_id',
        'page_type',
        'page_entity_id',
        'page_url',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected static function newFactory(): Factory
    {
        return AnalyticsEventModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
