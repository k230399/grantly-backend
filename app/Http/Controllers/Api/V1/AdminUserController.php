<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Mail\AdminInvite;
use App\Models\User;
use App\Services\SupabaseAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

// Admin-only user + role management. All routes here sit behind the 'admin' middleware.
class AdminUserController extends Controller
{
    // GET /api/v1/admin/users — admins backing the Team screen. Applicants are intentionally
    // excluded: admins are added by email invite (or by promoting an existing account through
    // the invite form), so there's no need to list the whole applicant roster here.
    public function index(): JsonResponse
    {
        $users = User::query()
            ->where('role', 'admin')
            ->orderBy('full_name')
            ->get(['id', 'email', 'full_name', 'role', 'organisation_name', 'created_at']);

        // Flag invited-but-not-yet-set-up accounts (never signed in). Best-effort: if the Supabase
        // lookup is unconfigured or unreachable, fall back to "nothing pending" rather than
        // breaking the page.
        $signedIn = [];
        try {
            $signedIn = app(SupabaseAdminService::class)->signedInByEmail();
        } catch (RuntimeException $e) {
            $signedIn = [];
        }

        return response()->json([
            'data' => $users->map(function (User $u) use ($signedIn) {
                $email   = strtolower((string) $u->email);
                // Pending only when we positively know they've never signed in.
                $pending = array_key_exists($email, $signedIn) && $signedIn[$email] === false;

                return $this->toArray($u, $pending);
            })->all(),
        ]);
    }

    // POST /api/v1/admin/admins — make someone an admin by email.
    // Existing profile -> promote in place (no email). New email -> generate an invite link and
    // send it through Grantly's mailer, then mirror an admin profile.
    public function invite(Request $request): JsonResponse
    {
        $data = Validator::make($request->all(), [
            'email'     => ['required', 'email'],
            'full_name' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $email = strtolower(trim($data['email']));

        // Promotion path: the person already has an account.
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if ($existing->role !== 'admin') {
                $existing->update(['role' => 'admin']);
            }

            return response()->json([
                'data'    => $this->toArray($existing->fresh()),
                'action'  => 'promoted',
                'message' => "{$existing->full_name} is now an admin.",
            ]);
        }

        // Invite path: generate the link (creates the auth user, sends nothing), then email it
        // ourselves and mirror an admin profile so the row exists the moment they accept.
        try {
            $admin = app(SupabaseAdminService::class);
        } catch (RuntimeException $e) {
            return $this->serviceUnavailable();
        }

        try {
            $invite = $admin->generateInviteLink($email);
        } catch (RuntimeException $e) {
            return $this->errorForCode($e->getMessage());
        }

        if (! $invite['id']) {
            return $this->serviceUnavailable();
        }

        $user = User::create([
            'id'        => $invite['id'],
            'email'     => $email,
            'full_name' => $data['full_name'] ?? '',
            'role'      => 'admin',
        ]);

        // Sent inline so the admin gets an accurate result. The account already exists and shows
        // as Pending, so if delivery fails they can use Resend rather than losing the invite.
        try {
            Mail::to($email)->send(new AdminInvite($email, $invite['url']));
        } catch (\Throwable $e) {
            return response()->json([
                'data'    => $this->toArray($user, true),
                'action'  => 'invited',
                'message' => "Admin added, but the invite email could not be sent. Use Resend to try again.",
            ], 201);
        }

        return response()->json([
            'data'    => $this->toArray($user, true),
            'action'  => 'invited',
            'message' => "Invitation sent to {$email}.",
        ], 201);
    }

    // POST /api/v1/admin/admins/{user}/resend — re-send the invite to a pending admin who never
    // accepted. Generates a fresh link and emails it again. Refused once the account is confirmed.
    public function resend(User $user): JsonResponse
    {
        if ($user->role !== 'admin') {
            return $this->conflict('Only pending admin invitations can be resent.');
        }

        try {
            $admin = app(SupabaseAdminService::class);
        } catch (RuntimeException $e) {
            return $this->serviceUnavailable();
        }

        // Guard against resending to someone who has already finished setting up (signed in).
        try {
            $signedIn = $admin->signedInByEmail();
            $email    = strtolower((string) $user->email);
            if (($signedIn[$email] ?? false) === true) {
                return $this->conflict('This person has already accepted their invite.');
            }
        } catch (RuntimeException $e) {
            // Status lookup unavailable — proceed with the resend rather than blocking the admin.
        }

        try {
            $invite = $admin->generateInviteLink($user->email);
        } catch (RuntimeException $e) {
            return $this->errorForCode($e->getMessage());
        }

        try {
            Mail::to($user->email)->send(new AdminInvite($user->email, $invite['url']));
        } catch (\Throwable $e) {
            return response()->json([
                'error' => [
                    'code'    => 'email_send_failed',
                    'message' => 'Could not send the invitation email. Please try again.',
                ],
            ], 502);
        }

        return response()->json([
            'message' => "Invitation resent to {$user->email}.",
        ]);
    }

    // PATCH /api/v1/admin/users/{user}/role — promote or demote. Guarded so an admin cannot
    // lock themselves out (own role) or strand the system with no admins (last admin).
    public function updateRole(UpdateUserRoleRequest $request, User $user): JsonResponse
    {
        $role = $request->validated()['role'];

        if ($user->id === $request->user()->id) {
            return $this->conflict('You cannot change your own role.');
        }

        if ($user->role === 'admin' && $role !== 'admin' && User::where('role', 'admin')->count() <= 1) {
            return $this->conflict('You cannot demote the last remaining admin.');
        }

        if ($user->role !== $role) {
            $user->update(['role' => $role]);
        }

        return response()->json(['data' => $this->toArray($user->fresh())]);
    }

    private function toArray(User $user, bool $pending = false): array
    {
        return [
            'id'                => $user->id,
            'email'             => $user->email,
            'full_name'         => $user->full_name,
            'role'              => $user->role,
            'organisation_name' => $user->organisation_name,
            'pending'           => $pending,
            'created_at'        => $user->created_at?->toIso8601String(),
        ];
    }

    private function conflict(string $message): JsonResponse
    {
        return response()->json([
            'error' => ['code' => 'role_change_not_allowed', 'message' => $message],
        ], 422);
    }

    private function serviceUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => [
                'code'    => 'invite_unavailable',
                'message' => 'Admin invites are not available right now. Please try again later.',
            ],
        ], 503);
    }

    // Maps SupabaseAdminService error codes to the project's standard error envelope.
    private function errorForCode(string $code): JsonResponse
    {
        return match ($code) {
            'email_taken' => response()->json([
                'error' => ['code' => 'email_taken', 'message' => 'An account with this email already exists.'],
            ], 422),

            'rate_limited' => response()->json([
                'error' => ['code' => 'rate_limited', 'message' => 'Too many invites just now. Please wait a moment and try again.'],
            ], 429),

            'not_configured' => $this->serviceUnavailable(),

            default => response()->json([
                'error' => ['code' => 'invite_failed', 'message' => 'Could not send the invitation. Please try again.'],
            ], 502),
        };
    }
}
