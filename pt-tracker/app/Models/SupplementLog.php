<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'supplement_id', 'taken_at', 'amount', 'unit', 'notes'])]
class SupplementLog extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    /** @return BelongsTo<Supplement, $this> */
    public function supplement(): BelongsTo
    {
        return $this->belongsTo(Supplement::class, 'supplement_id');
    }
}
