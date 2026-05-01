<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents an applicant's request for funding from a specific grant round.
 * Status lifecycle: draft → submitted → under_review → approved | rejected
 */
class Application extends Model
{
    use HasFactory, HasUuids;

    // Computed attributes to include automatically in every JSON/array response.
    // reference_number is the formatted display string (e.g. APP-2026-000042).
    // Note: ref_number (the raw integer) is intentionally NOT in $appends — we
    // only expose the formatted version to avoid confusing callers with two IDs.
    protected $appends = ['reference_number'];

    // These fields can be mass-assigned via create() or fill().
    // Fields not listed here are protected and must be set individually.
    // Note: ref_number is deliberately absent — the database sequence assigns it,
    // not the application code. Adding it here would let callers override it.
    protected $fillable = [
        'applicant_id',
        'grant_round_id',
        'project_name',
        'project_description',
        'funding_requested',
        'total_project_budget',
        'declaration_accepted',
        'form_data',
        'status',
        'submitted_at',
    ];

    // Type casting — converts database values to the right PHP types automatically
    protected function casts(): array
    {
        return [
            'ref_number'           => 'integer',   // Always an int in PHP; PDO can return it as a string otherwise
            'funding_requested'    => 'decimal:2', // Always show as e.g. 5000.00
            'total_project_budget' => 'decimal:2',
            'declaration_accepted' => 'boolean',   // Stored as 0/1 in DB, accessed as true/false in PHP
            // form_data stores the applicant's answers to the custom questions defined
            // by the grant round's application_form_schema. 'json' decodes the JSONB
            // column into a PHP array on read and re-encodes on write.
            'form_data'            => 'json',
            'submitted_at'         => 'datetime',  // Becomes a Carbon date object when not null
        ];
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Formats the raw sequence integer into a human-readable reference number.
     *
     * The database stores a plain integer in the ref_number column (e.g. 42).
     * This accessor returns the display string (e.g. "APP-2026-000042") whenever
     * you read $application->reference_number or the model is serialised to JSON.
     *
     * Because this method is listed in $appends, it's included in every API response
     * automatically — you don't need to add it manually in each controller.
     *
     * The year is taken from created_at (when the application was first created),
     * not the current year. This means a 2025 application always shows APP-2025-XXXXXX
     * even if you're viewing it in 2027.
     */
    protected function referenceNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Guard: if ref_number hasn't been set yet (can happen during testing
                // before the DB assigns the sequence value), return null gracefully.
                if (is_null($this->ref_number)) {
                    return null;
                }

                // Use the year the application was originally created, not today's year.
                // The ?-> "null-safe" operator handles the edge case where created_at
                // hasn't been set yet (e.g. a freshly instantiated but unsaved model).
                $year = $this->created_at?->format('Y') ?? now()->format('Y');

                // sprintf with %06d zero-pads the integer to at least 6 digits.
                // 42 → "000042", 10000 → "010000", 1000000 → "1000000" (still works past 6 digits)
                return sprintf('APP-%s-%06d', $year, $this->ref_number);
            }
        );
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The applicant (user) who owns this application.
     * Returns the User (profile) record for the applicant.
     */
    public function applicant(): BelongsTo
    {
        // 'applicant_id' is the foreign key column on this table pointing to profiles.id
        return $this->belongsTo(User::class, 'applicant_id');
    }

    /**
     * The grant round this application is for.
     * Returns the GrantRound model.
     */
    public function grantRound(): BelongsTo
    {
        return $this->belongsTo(GrantRound::class, 'grant_round_id');
    }

    /**
     * All documents uploaded as supporting material for this application.
     * Returns a collection of ApplicationDocument models.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    /**
     * The full history of status changes on this application — the audit trail.
     * Returns a collection of ApplicationStatusHistory models, ordered oldest first.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('changed_at', 'asc');
    }

    /**
     * Admin review notes left on this application.
     * Returns a collection of ReviewNote models.
     */
    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ReviewNote::class);
    }

    /**
     * Notifications related to this application.
     * Returns a collection of Notification models.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }
}
