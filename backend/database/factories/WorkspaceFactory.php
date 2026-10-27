<?php

namespace Database\Factories;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Workspace>
 */
class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'description' => null,
            'icon' => null,
            'color' => null,
            'sort_order' => 0,
        ];
    }

    /**
     * @param  array<int, string>  $keys
     */
    public function withKeys(array $keys): static
    {
        return $this->afterCreating(function (Workspace $workspace) use ($keys): void {
            foreach ($keys as $key) {
                $workspace->items()->create(['navigation_key' => $key]);
            }
        });
    }
}
