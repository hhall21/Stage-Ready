<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['cycle_id', 'substance', 'taken_at', 'amount', 'unit', 'route', 'notes'])]
class CycleEntry extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'taken_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Phase, $this> */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(Phase::class, 'cycle_id');
    }
}
