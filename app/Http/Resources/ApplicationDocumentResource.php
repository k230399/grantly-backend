<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationDocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'application_id'  => $this->application_id,
            'file_name'       => $this->file_name,
            'file_type'       => $this->file_type,
            'document_type'   => $this->document_type,
            'form_field_id'   => $this->form_field_id,
            'document_request_id' => $this->document_request_id,
            'file_size_bytes' => (int) $this->file_size_bytes,
            'storage_path'    => $this->storage_path,
            // The controller attaches a short-lived signed URL as a transient property before resourcing.
            'download_url'    => $this->resource->download_url ?? null,
            'uploaded_at'     => $this->uploaded_at?->toIso8601String(),
        ];
    }
}
