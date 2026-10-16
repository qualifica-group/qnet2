<?php

namespace App\DataObjects\JobRoles;

/**
 * Validated payload for creating or partially updating a job role (spec 0271).
 * `job_activity_ids` absent means "unchanged" (null), present means full replace.
 */
final readonly class JobRoleData
{
    /**
     * @param  array<string, mixed>  $submitted  validated name/description keys
     * @param  array<int, int>|null  $jobActivityIds  null = leave the links untouched
     */
    private function __construct(private array $submitted, private ?array $jobActivityIds) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            array_intersect_key($data, array_flip(['name', 'description'])),
            array_key_exists('job_activity_ids', $data) ? array_values($data['job_activity_ids'] ?? []) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->submitted;
    }

    /**
     * @return array<int, int>|null
     */
    public function jobActivityIds(): ?array
    {
        return $this->jobActivityIds;
    }
}
