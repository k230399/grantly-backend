<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Represents a user profile — both applicants and admins.
 * Mirrors the row in auth.users managed by Supabase; the UUID here matches Supabase's user ID.
 * Role controls access: 'applicant' sees their own data; 'admin' sees everything.
 */
class User extends Authenticatable
{
    // We don't use Laravel's built-in Notifiable trait because this project uses
    // its own notifications table (and Resend/Supabase for email) rather than Laravel's
    // built-in notification system.
    use HasFactory;

    // The users are stored in the 'profiles' table rather than the default 'users' table.
    // This mirrors the Supabase auth.users table — the UUID here matches the Supabase user ID.
    protected $table = 'profiles';

    // Disable auto-increment because the primary key is a UUID string set externally by Supabase
    public $incrementing = false;

    // Tell Laravel the primary key is a string (UUID), not an integer
    protected $keyType = 'string';

    // These fields can be set when creating or updating a user profile.
    // The 'id' is included because we set it explicitly from Supabase's UUID.
    protected $fillable = [
        'id',
        'email',
        'full_name',
        'organisation_name',
        'abn',
        'phone',
        'address',
        'state',
        'postcode',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * Grant rounds created by this admin user.
     * Returns a collection of GrantRound models.
     */
    public function grantRounds(): HasMany
    {
        return $this->hasMany(GrantRound::class, 'created_by');
    }

    /**
     * Applications submitted by this applicant.
     * Returns a collection of Application models.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    /**
     * Status change history records where this user was the one who made the change.
     * Returns a collection of ApplicationStatusHistory models.
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'changed_by');
    }

    /**
     * Review notes written by this admin user.
     * Returns a collection of ReviewNote models.
     */
    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ReviewNote::class, 'reviewer_id');
    }

    /**
     * In-app notifications for this user.
     * Returns a collection of Notification models ordered newest first.
     */
    public function appNotifications(): HasMany
    {
        // Named 'appNotifications' (not 'notifications') to avoid conflicting with
        // Laravel's built-in Notifiable trait method, even though we're not using that trait.
        return $this->hasMany(Notification::class, 'user_id')->orderBy('created_at', 'desc');
    }
}
