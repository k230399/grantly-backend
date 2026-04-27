<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a file uploaded as part of an application.
 * Stores metadata only — the actual file lives in Supabase Storage.
 * Laravel issues signed URLs so the browser can download files securely.
 */
class ApplicationDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'file_name',
        'file_type',
        'storage_path',
        'document_type',
        'file_size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            // uploaded_at is the business-meaningful timestamp for when the file arrived
            'uploaded_at' => 'datetime',
        ];
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * The application this document belongs to.
     * Returns the Application model.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
