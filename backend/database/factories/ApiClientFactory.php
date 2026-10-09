<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiClient>
 */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' integration',
            'description' => null,
            'rate_limit_per_minute' => null,
            'expires_at' => null,
            'is_active' => true,
            'key_last_four' => null,
            'created_by' => null,
        ];
    }

    /**
     * Mirrors ApiClientService: every client owns a technical user. The
     * super-admin role is assigned by the service, not here.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ApiClient $client): void {
            if ($client->service_user_id === null) {
                $client->service_user_id = User::factory()->serviceAccount()->create([
                    'name' => 'API · '.$client->name,
                ])->id;
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
