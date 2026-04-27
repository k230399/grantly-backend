<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a funding opportunity created by an admin.
 * Status lifecycle: draft → open → closed (→ completed)
 * Only published rounds (is_published = true) are visible to applicants.
 */
class GrantRound extends Model
{
    // HasUuids tells Laravel to auto-generate a UUID when a new record is created,
    // and to treat the primary key as a string rather than an auto-incrementing integer.
    use HasFactory, HasUuids;

    protected $table = 'grant_rounds';

    // Every field that can be set when creating or updating a grant round.
    // Laravel blocks any field not in this list from being mass-assigned (security protection).
    protected $fillable = [
        // Core details
        'title',
        'short_description',
        'description',
        'cover_image_url',

        // Eligibility
        'eligible_organisation_types',
        'geographic_restrictions',
        'eligibility_criteria',

        // Application requirements
        'required_documents',       // stored as a Postgres text array
        'assessment_criteria',
        'key_focus_areas',          // stored as a Postgres text array
        'application_form_schema',  // stored as JSONB

        // Funding amounts
        'min_funding_amount',
        'max_funding_amount',
        'total_funding_pool',

        // Status and visibility
        'status',
        'is_published',
        'is_featured',
        'allow_multiple_applications',
        'max_applications_per_user',

        // Schedule
        'opens_at',
        'closes_at',
        'assessment_period_start',
        'notification_date',
        'funding_release_date',
        'published_at',
        'closed_at',

        // Contact
        'contact_email',
        'contact_phone',

        // Ownership
        'created_by',
        'updated_by',
    ];

    // Tell Laravel how to convert database values when you read them in PHP.
    // 'datetime' → Carbon object, so you can do $round->opens_at->format('d/m/Y')
    // 'decimal:2' → always has 2 decimal places
    // 'boolean'   → true/false instead of 1/0
    // 'array'     → PHP array ↔ Postgres text[] (stored as JSON string by Eloquent)
    // 'json'      → PHP array/object ↔ JSON string
    protected function casts(): array
    {
        return [
            // Dates — all returned as Carbon objects
            'opens_at'                => 'datetime',
            'closes_at'               => 'datetime',
            'assessment_period_start' => 'datetime',
            'notification_date'       => 'datetime',
            'funding_release_date'    => 'datetime',
            'published_at'            => 'datetime',
            'closed_at'               => 'datetime',
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',

            // Money amounts — always 2 decimal places
            'min_funding_amount' => 'decimal:2',
            'max_funding_amount' => 'decimal:2',
            'total_funding_pool' => 'decimal:2',

            // Boolean flags — stored as true/false in Postgres
            'is_published'              => 'boolean',
            'is_featured'               => 'boolean',
            'allow_multiple_applications' => 'boolean',

            // Arrays — Eloquent serialises these as JSON strings when saving,
            // and deserialises them back to PHP arrays when reading.
            // Note: Postgres native text[] is handled transparently this way.
            'required_documents' => 'array',
            'key_focus_areas'    => 'array',

            // JSONB — the custom application form schema, stored as a JSON object
            'application_form_schema' => 'json',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The admin user who created this grant round.
     * Returns the User (profile) record for the creator.
     */
    public function creator(): BelongsTo
    {
        // 'created_by' is the foreign key column on this table pointing to profiles.id
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The admin user who last updated this grant round.
     * Returns the User (profile) record, or null if never updated.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * All applications that have been submitted against this grant round.
     * Returns a collection of Application models.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'grant_round_id');
    }
}
