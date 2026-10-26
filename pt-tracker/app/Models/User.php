<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\DateBounds;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string $role
 * @property bool $is_active
 * @property bool $is_bodybuilding_coach
 * @property Carbon|null $date_of_birth
 * @property string|null $phone
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'date_of_birth', 'phone'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
        'is_bodybuilding_coach' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'date_of_birth' => 'date',
            'is_active' => 'boolean',
            'is_bodybuilding_coach' => 'boolean',
        ];
    }

    /** @return BelongsToMany<User, $this> */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trainer_clients', 'trainer_id', 'client_id')
            ->withPivot(['id', 'start_date', 'end_date', 'status'])->withTimestamps();
    }

    /** @return BelongsToMany<User, $this> */
    public function trainers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'trainer_clients', 'client_id', 'trainer_id')
            ->withPivot(['id', 'start_date', 'end_date', 'status'])->withTimestamps();
    }

    public function hasBodybuildingCoach(): bool
    {
        return $this->activeCoaches()->where('users.is_bodybuilding_coach', true)->exists();
    }

    public function hasActiveCoach(): bool
    {
        return $this->activeCoaches()->exists();
    }

    /** @return BelongsToMany<User, $this> */
    private function activeCoaches(): BelongsToMany
    {
        return $this->trainers()->where('users.role', 'coach')
            ->where('users.is_active', true)
            ->wherePivot('status', 'active')
            ->where('trainer_clients.start_date', '<', DateBounds::after(today()->toDateString()))
            ->where(fn ($query) => $query->whereNull('trainer_clients.end_date')
                ->orWhereDate('trainer_clients.end_date', '>=', today()->toDateString()));
    }

    /** @return HasMany<TrainerClient, $this> */
    public function trainerAssignments(): HasMany
    {
        return $this->hasMany(TrainerClient::class, 'trainer_id');
    }

    /** @return HasMany<TrainerClient, $this> */
    public function clientAssignments(): HasMany
    {
        return $this->hasMany(TrainerClient::class, 'client_id');
    }

    /** @return HasMany<DailyCheckIn, $this> */
    public function dailyCheckIns(): HasMany
    {
        return $this->hasMany(DailyCheckIn::class, 'client_id');
    }

    /** @return HasMany<Meal, $this> */
    public function meals(): HasMany
    {
        return $this->hasMany(Meal::class, 'client_id');
    }

    /** @return HasMany<Workout, $this> */
    public function workouts(): HasMany
    {
        return $this->hasMany(Workout::class, 'client_id');
    }

    /** @return HasMany<Mesocycle, $this> */
    public function mesocycles(): HasMany
    {
        return $this->hasMany(Mesocycle::class, 'client_id');
    }

    /** @return HasMany<Workout, $this> */
    public function assignedWorkouts(): HasMany
    {
        return $this->hasMany(Workout::class, 'trainer_id');
    }

    /** @return HasMany<TrainingClip, $this> */
    public function trainingClips(): HasMany
    {
        return $this->hasMany(TrainingClip::class, 'client_id');
    }

    /** @return HasMany<ClipFeedback, $this> */
    public function clipFeedback(): HasMany
    {
        return $this->hasMany(ClipFeedback::class, 'trainer_id');
    }

    /** @return HasMany<SupplementLog, $this> */
    public function supplementLogs(): HasMany
    {
        return $this->hasMany(SupplementLog::class, 'client_id');
    }

    /** @return HasMany<Phase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(Phase::class, 'client_id');
    }

    /** @return HasMany<Competition, $this> */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class, 'client_id');
    }
}
