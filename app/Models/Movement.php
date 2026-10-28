<?php

namespace App\Models;

use App\Enums\MovementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Movement extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'movement_date',
        'from_fund_id',
        'to_fund_id',
        'entity_id',
        'category_id',
        'amount',
        'concept',
        'reference',
        'note',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'movement_date' => 'date:Y-m-d',
            'amount' => 'string',
        ];
    }

    public function fromFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'from_fund_id');
    }

    public function toFund(): BelongsTo
    {
        return $this->belongsTo(Fund::class, 'to_fund_id');
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function void(): HasOne
    {
        return $this->hasOne(MovementVoid::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(MovementAttachment::class);
    }

    public function isVoided(): bool
    {
        return $this->void !== null;
    }
}
