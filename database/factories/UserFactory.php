<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

// Factory for the profiles table. UUIDs are explicit because Supabase Auth
// owns the user ID and our profile row's id must match it.
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'id'        => (string) Str::uuid(),
            'email'     => fake()->unique()->safeEmail(),
            'full_name' => fake()->name(),
            'role'      => 'applicant',
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }
}
