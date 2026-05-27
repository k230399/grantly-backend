<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

// Admin asks the applicant for an additional supporting document. The applicant
// uploads via the existing application_documents pipeline and the row links back
// here through application_documents.document_request_id.
class DocumentRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'requested_by',
        'label',
        'description',
        'status',
        'requested_at',
        'fulfilled_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    // One file per request (locked at the product level). The hasOne relationship
    // is convenient even though the FK lives on application_documents.
    public function document(): HasOne
    {
        return $this->hasOne(ApplicationDocument::class, 'document_request_id');
    }
}
