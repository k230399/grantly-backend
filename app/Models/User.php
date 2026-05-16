<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasFactory;

    // The users are stored in the 'profiles' table; the UUID matches the Supabase user ID.
    protected $table = 'profiles';

    public $incrementing = false;
    protected $keyType = 'string';

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

    public function grantRounds(): HasMany
    {
        return $this->hasMany(GrantRound::class, 'created_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class, 'changed_by');
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ReviewNote::class, 'reviewer_id');
    }

    // Named 'appNotifications' to avoid clashing with Laravel's Notifiable trait method.
    public function appNotifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'user_id')->orderBy('created_at', 'desc');
    }
}
