<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $stripe_customer_id
 * @property string|null $stripe_subscription_id
 * @property string|null $checkout_session_id
 * @property CarbonImmutable|null $checkout_expires_at
 * @property string $status
 * @property bool $cancel_at_period_end
 * @property CarbonImmutable|null $last_paid_at
 * @property CarbonImmutable|null $paid_through
 * @property CarbonImmutable|null $retention_expires_at
 * @property CarbonImmutable|null $data_purged_at
 * @property int $last_status_event_at
 */
class CoachMembership extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['checkout_expires_at' => 'immutable_datetime', 'last_paid_at' => 'immutable_datetime',
            'paid_through' => 'immutable_datetime', 'retention_expires_at' => 'immutable_datetime',
            'data_purged_at' => 'immutable_datetime', 'cancel_at_period_end' => 'boolean', 'last_status_event_at' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function coach(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
