<?php

namespace App\DataObjects\JobActivities;

/**
 * Validated payload for creating or partially updating a job activity
 * (spec 0271). Only the submitted keys are applied; the link to job roles is
 * edited from the role side (D-5).
 */
final readonly class JobActivityData
{
    /**
     * @param  array<string, mixed>  $submitted  validated name/description keys
     */
    private function __construct(private array $submitted) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(array_intersect_key($data, array_flip(['name', 'description'])));
    }

    /**
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->submitted;
    }
}
