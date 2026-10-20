<?php

namespace App\Models;

use App\Models\Abstracts\BaseModel;
use App\Models\Concerns\LogsModelActivity;
use Database\Factories\SkillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Skill catalogue entry (spec 0286 D-2). A skill assigned to a person is
 * deactivated, never deleted.
 */
#[Fillable(['name', 'category', 'description', 'is_active'])]
class Skill extends BaseModel
{
    /** @use HasFactory<SkillFactory> */
    use HasFactory, LogsModelActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * Job roles requiring this skill; the pivot carries `min_level`.
     *
     * @return BelongsToMany<JobRole, $this>
     */
    public function jobRoles(): BelongsToMany
    {
        return $this->belongsToMany(JobRole::class, 'job_role_skill')->withPivot('min_level');
    }

    /**
     * @return HasMany<UserSkill, $this>
     */
    public function userSkills(): HasMany
    {
        return $this->hasMany(UserSkill::class);
    }
}
