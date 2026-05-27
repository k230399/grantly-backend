<?php

namespace Database\Factories;

use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class GrantRoundFactory extends Factory
{
    protected $model = GrantRound::class;

    public function definition(): array
    {
        return [
            'id'                   => (string) Str::uuid(),
            'title'                => fake()->sentence(3),
            'description'          => fake()->paragraph(),
            'max_funding_amount'   => 10000,
            'min_funding_amount'   => 1000,
            'eligibility_criteria' => 'Open to incorporated AU not-for-profits.',
            'status'               => 'open',
            'is_published'         => true,
            'opens_at'             => now()->subDays(7),
            'closes_at'             => now()->addDays(30),
            'created_by'           => User::factory()->admin(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft', 'is_published' => false]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed', 'closed_at' => now()]);
    }
}
