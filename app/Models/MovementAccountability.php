<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MovementAccountability extends Model
{
    use HasFactory;

    protected $fillable = [
        'movement_id',
        'rendered_at',
        'amount',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rendered_at' => 'date',
            'amount' => 'string',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(AccountabilityAttachment::class, 'accountability_id');
    }
}
