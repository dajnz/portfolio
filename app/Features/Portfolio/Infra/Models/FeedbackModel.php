<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Models;

use Database\Factories\FeedbackModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the `feedback` table. Submissions are immutable apart
 * from the read/forwarded flags — `created_at` only, no `updated_at`.
 *
 * @property int $id
 * @property string $sender_email
 * @property string $sender_name
 * @property string $message
 * @property string $ip_address
 * @property string $fingerprint
 * @property bool $is_read
 * @property bool $is_forwarded
 * @property \Illuminate\Support\Carbon $created_at
 */
final class FeedbackModel extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    public const UPDATED_AT = null;

    protected $fillable = [
        'sender_email',
        'sender_name',
        'message',
        'ip_address',
        'fingerprint',
        'is_read',
        'is_forwarded',
    ];

    protected static function newFactory(): Factory
    {
        return FeedbackModelFactory::new();
    }

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_forwarded' => 'boolean',
        ];
    }
}
