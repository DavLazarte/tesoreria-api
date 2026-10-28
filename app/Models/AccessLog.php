<?php

namespace App\Models;

use App\Enums\AccessLogEvent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccessLog extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'email_attempted',
        'event',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'event' => AccessLogEvent::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
