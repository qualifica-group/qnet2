<?php

namespace Database\Factories;

use App\Enums\SkillLevel;
use App\Models\Skill;
use App\Models\User;
use App\Models\UserSkill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserSkill>
 */
class UserSkillFactory extends Factory
{
    protected $model = UserSkill::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'skill_id' => Skill::factory(),
            'level' => SkillLevel::Intermediate,
            'assessed_on' => fake()->dateTimeBetween('-1 year', '-1 day')->format('Y-m-d'),
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function atLevel(SkillLevel $level): static
    {
        return $this->state(fn () => ['level' => $level]);
    }
}
