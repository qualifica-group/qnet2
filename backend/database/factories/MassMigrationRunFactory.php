<?php

namespace Database\Factories;

use App\Enums\MigrationStatus;
use App\Models\MassMigrationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MassMigrationRun>
 */
class MassMigrationRunFactory extends Factory
{
    protected $model = MassMigrationRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'sources' => ['companies', 'users'],
            'status' => MigrationStatus::Pending,
            'update_existing' => false,
        ];
    }
}
