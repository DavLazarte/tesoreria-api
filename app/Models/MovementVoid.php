<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovementVoid extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $primaryKey = 'movement_id';

    public $incrementing = false;

    protected $fillable = [
        'movement_id',
        'voided_by',
        'reason',
        'voided_at',
    ];

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
        ];
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(Movement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
