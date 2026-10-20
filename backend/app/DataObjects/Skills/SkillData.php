<?php

namespace App\DataObjects\Skills;

/**
 * Validated payload for creating or partially updating a skill (spec 0286).
 * `job_roles` absent means "unchanged" (null), present means full replace.
 */
final readonly class SkillData
{
    private const array ATTRIBUTES = ['name', 'category', 'description', 'is_active'];

    /**
     * @param  array<string, mixed>  $submitted  validated skill columns
     * @param  array<int, int>|null  $minLevels  job role id => minimum level; null = leave untouched
     */
    private function __construct(private array $submitted, private ?array $minLevels) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        $minLevels = null;

        if (array_key_exists('job_roles', $data)) {
            $minLevels = [];

            foreach ($data['job_roles'] ?? [] as $role) {
                $minLevels[(int) $role['id']] = (int) $role['min_level'];
            }
        }

        return new self(array_intersect_key($data, array_flip(self::ATTRIBUTES)), $minLevels);
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->submitted;
    }

    /**
     * @return array<int, array{min_level: int}>|null sync payload: job role id => pivot
     */
    public function jobRoleSync(): ?array
    {
        return $this->minLevels === null
            ? null
            : array_map(static fn (int $level): array => ['min_level' => $level], $this->minLevels);
    }
}
