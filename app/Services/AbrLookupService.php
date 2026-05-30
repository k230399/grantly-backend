<?php

namespace App\Services;

use App\Support\Abn;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

// Thin wrapper around the Australian Business Register's public JSON lookup
// (https://abr.business.gov.au/json/AbnDetails.aspx). One outbound call per
// ABN per hour: results are cached so a typing applicant plus the server-side
// PATCH /profile re-verify do not hit the ABR more than once.
//
// Throws RuntimeException with codes the controller maps to API errors:
//   not_configured, invalid_format, invalid_checksum, not_found, cancelled, lookup_failed
class AbrLookupService
{
    private string $baseUrl;
    private ?string $guid;
    private int $cacheTtl;

    public function __construct()
    {
        $this->baseUrl  = rtrim((string) config('services.abr.base_url'), '/');
        $this->guid     = config('services.abr.guid') ?: null;
        $this->cacheTtl = (int) config('services.abr.cache_ttl', 3600);
    }

    // Looks up a single ABN. Returns the normalised shape used across the app.
    public function lookup(string $abn): array
    {
        $abn = Abn::normalise($abn);

        if (! Abn::hasValidFormat($abn)) {
            throw new RuntimeException('invalid_format');
        }

        // Reject numbers that fail the ABR check-digit algorithm before spending a network
        // call: they can never resolve on the register, so this is a cheap local short-circuit.
        if (! Abn::hasValidChecksum($abn)) {
            throw new RuntimeException('invalid_checksum');
        }

        if ($this->guid === null || $this->guid === '') {
            throw new RuntimeException('not_configured');
        }

        // Cache hit avoids a second outbound call within the TTL window.
        $cacheKey = "abr:lookup:{$abn}";
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            $this->assertActive($cached);
            return $cached;
        }

        $payload = $this->fetch($abn);
        $normalised = $this->normalise($payload, $abn);

        Cache::put($cacheKey, $normalised, $this->cacheTtl);

        $this->assertActive($normalised);

        return $normalised;
    }

    // Issues the HTTP call to the ABR and returns the parsed JSON payload.
    private function fetch(string $abn): array
    {
        try {
            $response = Http::timeout(8)->get("{$this->baseUrl}/AbnDetails.aspx", [
                'abn'      => $abn,
                'callback' => 'callback',
                'guid'     => $this->guid,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ABR lookup network error', ['abn' => $abn, 'error' => $e->getMessage()]);
            throw new RuntimeException('lookup_failed');
        }

        if (! $response->successful()) {
            Log::warning('ABR lookup non-2xx', ['abn' => $abn, 'status' => $response->status()]);
            throw new RuntimeException('lookup_failed');
        }

        // The endpoint wraps the JSON in callback(...). Strip it before decoding.
        $body = trim($response->body());
        $body = preg_replace('/^\w+\(/', '', $body);
        $body = preg_replace('/\)\s*;?\s*$/', '', $body);

        $data = json_decode($body, true);
        if (! is_array($data)) {
            Log::warning('ABR lookup parse error', ['abn' => $abn, 'body' => $body]);
            throw new RuntimeException('lookup_failed');
        }

        return $data;
    }

    // Reduces the ABR's verbose payload to just the fields the app cares about.
    private function normalise(array $data, string $requestedAbn): array
    {
        // ABR returns Message non-empty when the ABN is not on the register.
        $message = trim((string) ($data['Message'] ?? ''));
        if ($message !== '' || ! isset($data['Abn']) || $data['Abn'] === '') {
            throw new RuntimeException('not_found');
        }

        $businessNames = [];
        foreach (($data['BusinessName'] ?? []) as $name) {
            if (is_array($name) && ! empty($name['OrganisationName'])) {
                $businessNames[] = (string) $name['OrganisationName'];
            }
        }

        $status = (string) ($data['AbnStatus'] ?? '');

        return [
            'abn'             => (string) $data['Abn'] ?: $requestedAbn,
            'entity_name'     => (string) ($data['EntityName'] ?? ''),
            'entity_type'     => (string) ($data['EntityTypeName'] ?? ''),
            'status'          => $status,
            'is_active'       => strcasecmp($status, 'Active') === 0,
            'state'           => (string) ($data['AddressState'] ?? '') ?: null,
            'postcode'        => (string) ($data['AddressPostcode'] ?? '') ?: null,
            'business_names'  => $businessNames,
        ];
    }

    // Cached + fresh results both run through this so a cached "Cancelled" still rejects.
    private function assertActive(array $normalised): void
    {
        if (! ($normalised['is_active'] ?? false)) {
            throw new RuntimeException('cancelled');
        }
    }
}
