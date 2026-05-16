<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GrantRound extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'grant_rounds';

    protected $fillable = [
        'title',
        'short_description',
        'description',
        'cover_image_url',

        'eligible_organisation_types',
        'geographic_restrictions',
        'eligibility_criteria',

        'required_documents',
        'assessment_criteria',
        'key_focus_areas',
        'application_form_schema',

        'min_funding_amount',
        'max_funding_amount',
        'total_funding_pool',

        'status',
        'is_published',
        'is_featured',
        'allow_multiple_applications',
        'max_applications_per_user',

        'opens_at',
        'closes_at',
        'assessment_period_start',
        'notification_date',
        'funding_release_date',
        'published_at',
        'closed_at',

        'contact_email',
        'contact_phone',

        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'opens_at'                => 'datetime',
            'closes_at'               => 'datetime',
            'assessment_period_start' => 'datetime',
            'notification_date'       => 'datetime',
            'funding_release_date'    => 'datetime',
            'published_at'            => 'datetime',
            'closed_at'               => 'datetime',
            'created_at'              => 'datetime',
            'updated_at'              => 'datetime',

            'min_funding_amount' => 'decimal:2',
            'max_funding_amount' => 'decimal:2',
            'total_funding_pool' => 'decimal:2',

            'is_published'                => 'boolean',
            'is_featured'                 => 'boolean',
            'allow_multiple_applications' => 'boolean',

            'required_documents' => 'array',
            'key_focus_areas'    => 'array',

            'application_form_schema' => 'json',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(Application::class, 'grant_round_id');
    }
}
