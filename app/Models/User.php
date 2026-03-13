<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone_number',
        'password',
        'interface_language',
        'active_course_id',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'full_name',
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
        ];
    }

    public function studiedLessons()
    {
        return $this->hasMany(UserStudiedLesson::class);
    }

    /**
     * Get the user's full name (combines first and last name)
     */
    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Get all revision items for this user.
     */
    public function revisionItems(): HasMany
    {
        return $this->hasMany(RevisionItem::class);
    }

    /**
     * Get all trophies earned by this user.
     */
    public function trophies(): HasMany
    {
        return $this->hasMany(UserTrophy::class);
    }

    /**
     * Get all points earned by this user.
     */
    public function points(): HasMany
    {
        return $this->hasMany(UserPoint::class);
    }

    /**
     * Get all leaderboard entries for this user.
     */
    public function leaderboardEntries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    /**
     * Get all payments made by this user.
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get all receipts for this user.
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Get all course enrollments for this user.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(CourseEnrollment::class);
    }

    /**
     * Get all entitlements for this user.
     */
    public function entitlements(): HasMany
    {
        return $this->hasMany(UserEntitlement::class);
    }

    /**
     * Get all capabilities (features) granted to this user.
     */
    public function capabilities()
    {
        return $this->hasManyThrough(UserCapability::class, UserEntitlement::class);
    }

    /**
     * Get the user's active course.
     */
    public function activeCourse()
    {
        return $this->belongsTo(Course::class, 'active_course_id');
    }

    /**
     * Get all payment tokens (saved cards) for this user.
     */
    public function paymentTokens(): HasMany
    {
        return $this->hasMany(PaymentToken::class);
    }

    /**
     * Get all streaks for this user.
     */
    public function streaks(): HasMany
    {
        return $this->hasMany(UserStreak::class);
    }

    /**
     * Check if the user has a specific capability via an active entitlement.
     *
     * @param string $featureCode The capability code (e.g., 'revision.access')
     * @param string|null $scopeType Optional scope type (e.g., 'App\Models\Course')
     * @param int|null $scopeId Optional scope ID
     * @return bool
     */
    public function hasCapability(string $featureCode, ?string $scopeType = null, ?int $scopeId = null): bool
    {
        // If a scope is provided, ensure it matches.
        // If no scope is provided (null), we are checking for "global" access or access to "any" instance?
        // Current logic:
        // If scopeType/Id is NULL, it matches capabilities where scope is NULL (Global).
        // If scopeType/Id is PROVIDED, it matches capabilities with THAT scope OR Global (null).

        // Wait, the previous implementation of hasCapability (which I'm replacing/updating)
        // was:
        /*
        return $this->entitlements()
            ->active()
            ->whereHas('capabilities', function ($q) use ($featureCode, $scopeType, $scopeId) {
                $q->where('feature_code', $featureCode);
                if ($scopeType !== null) $q->where('scope_type', $scopeType);
                if ($scopeId !== null) $q->where('scope_id', $scopeId);
            })
            ->exists();
        */
        // That implementation was "strict": if I ask for course 1, you must have capability for course 1.
        // It didn't automatically fallback to global.
        // Let's stick to the existing strict logic for the model method to avoid side effects,
        // and let FeatureAccessService handle higher-level logic if needed.

        return $this->entitlements()
            ->active()
            ->whereHas('capabilities', function ($q) use ($featureCode, $scopeType, $scopeId) {
                $q->where('feature_code', $featureCode);

                if ($scopeType !== null) {
                    $q->where('scope_type', $scopeType);
                }

                if ($scopeId !== null) {
                    $q->where('scope_id', $scopeId);
                }
            })
            ->exists();
    }
}
