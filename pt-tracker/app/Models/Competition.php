<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['client_id', 'competition_name', 'competition_date', 'location', 'category', 'target_weight', 'placing', 'notes'])]
class Competition extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'competition_date' => 'date',
            'target_weight' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }
}
