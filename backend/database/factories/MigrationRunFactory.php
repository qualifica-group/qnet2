<?php

namespace Database\Factories;

use App\Enums\MigrationStatus;
use App\Models\MigrationRun;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MigrationRun>
 */
class MigrationRunFactory extends Factory
{
    protected $model = MigrationRun::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source' => 'users',
            'user_id' => User::factory(),
            'status' => MigrationStatus::Pending,
            'total_rows' => 0,
            'created_rows' => 0,
            'skipped_rows' => 0,
            'updated_rows' => 0,
            'failed_rows' => 0,
            'report' => null,
            'update_existing' => false,
        ];
    }

    /**
     * The run has finished processing every page of the external source.
     */
    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => MigrationStatus::Completed,
            'total_rows' => 2,
            'created_rows' => 1,
            'skipped_rows' => 1,
            'failed_rows' => 0,
            'report' => [
                ['old_id' => 2, 'level' => 'warning', 'message' => 'Unresolved role reference.'],
            ],
        ]);
    }
}
