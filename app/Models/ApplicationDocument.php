<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Stores upload metadata only. The file itself lives in Supabase Storage.
class ApplicationDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'application_id',
        'file_name',
        'file_type',
        'storage_path',
        'document_type',
        'form_field_id',
        'file_size_bytes',
        'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
