<?php

namespace App\Models;

use App\Enums\FundKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fund extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'kind',
        'description',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'kind' => FundKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class, 'group_id');
    }

    public function outgoingMovements(): HasMany
    {
        return $this->hasMany(Movement::class, 'from_fund_id');
    }

    public function incomingMovements(): HasMany
    {
        return $this->hasMany(Movement::class, 'to_fund_id');
    }
}
