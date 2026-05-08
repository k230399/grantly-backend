<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's internal note on a specific application.
 * Review notes are admin-only — applicant-facing comms goes through
 * status-change notes on application_status_history.
 */
class ReviewNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'reviewer_id',
        'note_content',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The application this note is attached to.
     * Returns the Application model.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * The admin who wrote this note.
     * Returns the User (profile) record for the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        // 'reviewer_id' is the foreign key column pointing to profiles.id
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
