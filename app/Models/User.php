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
     * Get all streaks for this user.
     */
    public function streaks(): HasMany
    {
        return $this->hasMany(UserStreak::class);
    }
}
