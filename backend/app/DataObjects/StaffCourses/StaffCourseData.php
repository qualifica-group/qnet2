<?php

namespace App\DataObjects\StaffCourses;

/**
 * Validated payload for creating or partially updating a staff course (spec
 * 0284). `job_role_ids` absent means "unchanged" (null), present means full
 * replace.
 */
final readonly class StaffCourseData
{
    private const array ATTRIBUTES = ['name', 'description', 'duration_minutes', 'validity_months', 'is_active'];

    /**
     * @param  array<string, mixed>  $submitted  validated course columns
     * @param  array<int, int>|null  $jobRoleIds  null = leave the links untouched
     */
    private function __construct(private array $submitted, private ?array $jobRoleIds) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            array_intersect_key($data, array_flip(self::ATTRIBUTES)),
            array_key_exists('job_role_ids', $data) ? array_values($data['job_role_ids'] ?? []) : null,
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
    public function jobRoleIds(): ?array
    {
        return $this->jobRoleIds;
    }
}
