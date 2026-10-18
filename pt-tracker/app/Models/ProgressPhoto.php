<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['check_in_id', 'photo_type', 'file_path', 'pose_key', 'mime_type'])]
#[Hidden(['file_path'])]
class ProgressPhoto extends Model
{
    /** @return BelongsTo<DailyCheckIn, $this> */
    public function checkIn(): BelongsTo
    {
        return $this->belongsTo(DailyCheckIn::class, 'check_in_id');
    }
}
