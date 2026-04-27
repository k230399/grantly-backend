<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable, append-only record of a single status change on an application.
 * Every time an application's status changes, a new row is inserted here.
 * Rows are never updated or deleted — this is the audit trail.
 */
class ApplicationStatusHistory extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'application_status_history';

    protected $fillable = [
        'application_id',
        'changed_by',
        'previous_status',
        'new_status',
        'notes',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            // changed_at is the authoritative timestamp of when the status transition happened
            'changed_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The application that had its status changed.
     * Returns the Application model.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The user (admin or applicant) who triggered the status change.
     * Returns the User (profile) record for whoever made the change.
     */
    public function changedBy(): BelongsTo
    {
        // 'changed_by' is the foreign key column pointing to profiles.id
        return $this->belongsTo(User::class, 'changed_by');
    }
}
