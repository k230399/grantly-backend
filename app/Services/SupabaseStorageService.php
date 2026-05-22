<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

// Thin wrapper around Supabase Storage's REST API, called server-side with the service-role key.
// Uses the credentials already configured under config('services.supabase').
class SupabaseStorageService
{
    private string $baseUrl;
    private string $serviceKey;
    private string $bucket;

    public function __construct()
    {
        $url        = rtrim((string) config('services.supabase.url'), '/');
        $serviceKey = (string) config('services.supabase.service_key');
        $bucket     = (string) config('services.supabase.storage_bucket');

        if ($url === '' || $serviceKey === '' || $bucket === '') {
            throw new RuntimeException('Supabase storage is not configured. Check SUPABASE_URL, SUPABASE_SERVICE_KEY, SUPABASE_STORAGE_BUCKET.');
        }

        $this->baseUrl    = $url;
        $this->serviceKey = $serviceKey;
        $this->bucket     = $bucket;
    }

    // Uploads the file's raw bytes to the given object path. Throws if Supabase returns a non-2xx.
    public function upload(string $path, UploadedFile $file): void
    {
        $response = Http::withToken($this->serviceKey)
            ->withHeaders([
                'Content-Type' => $file->getMimeType() ?: 'application/octet-stream',
                // x-upsert lets re-uploads to the same path overwrite — useful when a draft replaces an attachment.
                'x-upsert' => 'true',
            ])
            ->withBody(file_get_contents($file->getRealPath()), $file->getMimeType() ?: 'application/octet-stream')
            ->post($this->objectUrl($path));

        if (! $response->successful()) {
            throw new RuntimeException("Supabase upload failed: {$response->status()} {$response->body()}");
        }
    }

    // Returns a short-lived signed URL the browser can use to download the object directly.
    public function signedUrl(string $path, int $expiresInSeconds = 3600): string
    {
        $response = Http::withToken($this->serviceKey)
            ->asJson()
            ->post("{$this->baseUrl}/storage/v1/object/sign/{$this->bucket}/{$path}", [
                'expiresIn' => $expiresInSeconds,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException("Supabase sign failed: {$response->status()} {$response->body()}");
        }

        $signed = (string) ($response->json('signedURL') ?? $response->json('signedUrl') ?? '');
        if ($signed === '') {
            throw new RuntimeException('Supabase sign returned empty URL.');
        }

        // The API returns a relative path; prepend the storage base URL so the browser gets an absolute URL.
        return "{$this->baseUrl}/storage/v1{$signed}";
    }

    // Best-effort delete. Errors bubble up so callers can decide whether to swallow them.
    public function delete(string $path): void
    {
        $response = Http::withToken($this->serviceKey)
            ->delete($this->objectUrl($path));

        if (! $response->successful()) {
            throw new RuntimeException("Supabase delete failed: {$response->status()} {$response->body()}");
        }
    }

    private function objectUrl(string $path): string
    {
        return "{$this->baseUrl}/storage/v1/object/{$this->bucket}/{$path}";
    }
}
