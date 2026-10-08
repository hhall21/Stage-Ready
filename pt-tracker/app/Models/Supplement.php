<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['supplement_name', 'description'])]
class Supplement extends Model
{
    /** @return HasMany<SupplementLog, $this> */
    public function logs(): HasMany
    {
        return $this->hasMany(SupplementLog::class, 'supplement_id');
    }
}
