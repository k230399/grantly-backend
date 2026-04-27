<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An admin's note on a specific application.
 * Notes can be internal (admin-only) or visible to the applicant (is_internal = false).
 */
class ReviewNote extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'reviewer_id',
        'note_content',
        'is_internal',
    ];

    protected function casts(): array
    {
        return [
            // is_internal stored as 0/1 in the database, accessed as true/false in PHP
            'is_internal' => 'boolean',
        ];
    }

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
