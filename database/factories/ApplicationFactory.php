<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\GrantRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'id'                   => (string) Str::uuid(),
            'applicant_id'         => User::factory(),
            'grant_round_id'       => GrantRound::factory(),
            'project_name'         => fake()->sentence(3),
            'project_description'  => fake()->paragraph(),
            'funding_requested'    => 5000,
            'total_project_budget' => 7500,
            'declaration_accepted' => true,
            'status'               => 'draft',
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status'       => 'submitted',
            'submitted_at' => now(),
        ]);
    }

    public function underReview(): static
    {
        return $this->state(fn () => [
            'status'       => 'under_review',
            'submitted_at' => now(),
        ]);
    }
}
