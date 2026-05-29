<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

// Server-side wrapper around Supabase Auth's admin endpoints, called with the service-role key.
// Used to mint or invite admin accounts. Mirrors SupabaseStorageService's use of the same
// credentials under config('services.supabase'). Errors are thrown as RuntimeExceptions whose
// message is a machine-readable code (email_taken, rate_limited, invite_failed, create_failed)
// so callers can map them to friendly messages, the same convention AbrLookupService uses.
class SupabaseAdminService
{
    private string $baseUrl;
    private string $serviceKey;

    public function __construct()
    {
        $url        = rtrim((string) config('services.supabase.url'), '/');
        $serviceKey = (string) config('services.supabase.service_key');

        if ($url === '' || $serviceKey === '') {
            throw new RuntimeException('not_configured');
        }

        $this->baseUrl    = $url;
        $this->serviceKey = $serviceKey;
    }

    // Creates an already-confirmed auth user (no verification email) and returns the Supabase
    // user record, including its 'id'. Used by the CLI bootstrap so a first admin can be created
    // without email delivery configured.
    public function createConfirmedUser(string $email, string $password): array
    {
        $response = $this->client()->post("{$this->baseUrl}/auth/v1/admin/users", [
            'email'         => $email,
            'password'      => $password,
            'email_confirm' => true,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->codeFor($response->json(), 'create_failed'));
        }

        return $response->json();
    }

    // Generates an invite action link WITHOUT sending an email (Supabase only emails via the
    // invite endpoint; generate_link returns the link for us to deliver ourselves through Resend).
    // Creates the auth user if they don't exist yet. The link verifies then redirects to
    // {frontend}/auth/confirm, which forwards to /auth/set-password. Returns
    // ['url' => actionLink, 'id' => supabaseUserId]; id may be null on older GoTrue versions.
    public function generateInviteLink(string $email): array
    {
        $redirectTo = rtrim((string) config('services.frontend.url'), '/') . '/auth/confirm';

        $response = $this->client()->post("{$this->baseUrl}/auth/v1/admin/generate_link", [
            'type'        => 'invite',
            'email'       => $email,
            'redirect_to' => $redirectTo,
        ]);

        if ($response->failed()) {
            throw new RuntimeException($this->codeFor($response->json(), 'invite_failed'));
        }

        $data = $response->json();

        // GoTrue has moved these fields across versions: action_link sits at the root or under
        // 'properties'; the user id at 'user.id', 'id', or 'user_id'.
        $url = $data['action_link'] ?? ($data['properties']['action_link'] ?? null);
        $id  = $data['user']['id'] ?? ($data['id'] ?? ($data['user_id'] ?? null));

        if (! $url) {
            throw new RuntimeException('invite_failed');
        }

        return ['url' => $url, 'id' => $id];
    }

    // Returns a map of lowercased email => hasSignedIn(bool) for every auth user, by paging the
    // admin list endpoint. Used to flag invited-but-not-yet-set-up accounts as "pending": an
    // invitee who clicks the link has their email confirmed by Supabase but still hasn't signed
    // in until they set a password, so last_sign_in_at is the signal that they actually finished.
    public function signedInByEmail(): array
    {
        $map     = [];
        $perPage = 200;

        // Cap the loop so a misbehaving response can never spin forever.
        for ($page = 1; $page <= 50; $page++) {
            $response = $this->client()->get("{$this->baseUrl}/auth/v1/admin/users", [
                'page'     => $page,
                'per_page' => $perPage,
            ]);

            if ($response->failed()) {
                throw new RuntimeException($this->codeFor($response->json(), 'lookup_failed'));
            }

            $users = $response->json('users') ?? [];
            foreach ($users as $u) {
                $email = strtolower((string) ($u['email'] ?? ''));
                if ($email === '') {
                    continue;
                }
                $map[$email] = ! empty($u['last_sign_in_at']);
            }

            if (count($users) < $perPage) {
                break;
            }
        }

        return $map;
    }

    // Admin endpoints need both the apikey header and a service-role bearer token.
    private function client()
    {
        return Http::withToken($this->serviceKey)
            ->withHeaders(['apikey' => $this->serviceKey])
            ->asJson();
    }

    // Translates Supabase's free-text error into a code, falling back to $default.
    private function codeFor(?array $body, string $default): string
    {
        $msg = strtolower((string) ($body['msg'] ?? $body['message'] ?? $body['error_description'] ?? ''));

        return match (true) {
            str_contains($msg, 'already') && str_contains($msg, 'registered') => 'email_taken',
            str_contains($msg, 'already been registered')                     => 'email_taken',
            str_contains($msg, 'rate limit')                                  => 'rate_limited',
            default                                                           => $default,
        };
    }
}
