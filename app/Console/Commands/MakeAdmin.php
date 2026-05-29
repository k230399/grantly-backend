<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\SupabaseAdminService;
use Illuminate\Console\Command;
use RuntimeException;

// One-time bootstrap for the first admin (who has no inviter). After this, admins invite
// each other from the /admin/team screen. Promotes an existing account in place, or creates
// a brand-new confirmed Supabase user + admin profile.
//
//   php artisan admin:make you@example.com --name="Your Name"
class MakeAdmin extends Command
{
    protected $signature = 'admin:make {email : Email of the account to make an admin} {--name= : Full name (only used when creating a new account)}';

    protected $description = 'Promote an existing user to admin, or create a new admin account';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email address.");
            return self::FAILURE;
        }

        // Promotion path: account already exists, so we never touch Supabase Auth.
        $existing = User::where('email', $email)->first();
        if ($existing) {
            if ($existing->role === 'admin') {
                $this->info("{$email} is already an admin. Nothing to do.");
                return self::SUCCESS;
            }

            $existing->update(['role' => 'admin']);
            $this->info("Promoted {$email} to admin.");
            return self::SUCCESS;
        }

        // Creation path: mint a confirmed Supabase auth user, then mirror an admin profile.
        try {
            $admin = app(SupabaseAdminService::class);
        } catch (RuntimeException $e) {
            $this->error('Supabase service key is not configured. Set SUPABASE_SERVICE_KEY in your .env.');
            return self::FAILURE;
        }

        $name     = (string) ($this->option('name') ?: $this->ask('Full name'));
        $password = (string) $this->secret('Password (min 8 characters)');

        if (strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');
            return self::FAILURE;
        }

        if ($password !== $this->secret('Confirm password')) {
            $this->error('Passwords did not match.');
            return self::FAILURE;
        }

        try {
            $created = $admin->createConfirmedUser($email, $password);
        } catch (RuntimeException $e) {
            $this->error('Could not create the Supabase auth user: '.$e->getMessage());
            return self::FAILURE;
        }

        $userId = $created['id'] ?? ($created['user']['id'] ?? null);
        if (! $userId) {
            $this->error('Supabase did not return a user id. Aborting.');
            return self::FAILURE;
        }

        User::create([
            'id'        => $userId,
            'email'     => $email,
            'full_name' => $name,
            'role'      => 'admin',
        ]);

        $this->info("Created admin account for {$email}. You can now log in.");
        return self::SUCCESS;
    }
}
