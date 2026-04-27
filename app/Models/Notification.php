<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An in-app notification for a user, e.g. "Your application was approved."
 * is_read starts as false and is set to true when the user views or dismisses it.
 * Transactional emails (Resend) are separate — this table powers the notification inbox in the UI.
 */
class Notification extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'application_id',
        'type',
        'message',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            // is_read stored as 0/1 in the database, accessed as true/false in PHP
            'is_read' => 'boolean',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The user this notification belongs to.
     * Returns the User (profile) record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The application this notification relates to (if any).
     * Can be null for general/system notifications.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
