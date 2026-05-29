<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory, HasUuids;

    // Expose the formatted reference_number (e.g. APP-2026-000042) in every JSON response.
    // ref_number (the raw integer) is intentionally not appended.
    protected $appends = ['reference_number'];

    // ref_number is intentionally absent: the database sequence assigns it.
    protected $fillable = [
        'applicant_id',
        'grant_round_id',
        'applicant_type',
        'abn',
        'organisation_name',
        'project_name',
        'project_description',
        'funding_requested',
        'total_project_budget',
        'declaration_accepted',
        'form_data',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'ref_number'           => 'integer',
            'funding_requested'    => 'decimal:2',
            'total_project_budget' => 'decimal:2',
            'declaration_accepted' => 'boolean',
            'form_data'            => 'json',
            'submitted_at'         => 'datetime',
        ];
    }

    // Formats the raw sequence integer into a display reference like "APP-2026-000042".
    // Year is taken from created_at so a 2025 application stays APP-2025-XXXXXX forever.
    protected function referenceNumber(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (is_null($this->ref_number)) {
                    return null;
                }

                $year = $this->created_at?->format('Y') ?? now()->format('Y');

                return sprintf('APP-%s-%06d', $year, $this->ref_number);
            }
        );
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_id');
    }

    public function grantRound(): BelongsTo
    {
        return $this->belongsTo(GrantRound::class, 'grant_round_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ApplicationDocument::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(ApplicationStatusHistory::class)->orderBy('changed_at', 'asc');
    }

    public function reviewNotes(): HasMany
    {
        return $this->hasMany(ReviewNote::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
