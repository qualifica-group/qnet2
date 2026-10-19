<?php

declare(strict_types=1);
use App\Models\BusinessFunction;

if (! function_exists('individualProfile')) {
    /**
     * A minimal valid individual personal_data block. The user's `name` is derived
     * from it (ADR 0012), so every create payload must carry one.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function individualProfile(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'New',
            'last_name' => 'Person',
        ], $overrides);
    }
}

if (! function_exists('membershipFunctionId')) {
    /**
     * A fresh business function id to submit as `employment.business_function_ids`:
     * the mandatory membership of an active person (spec 0275 D-4).
     */
    function membershipFunctionId(): int
    {
        return BusinessFunction::factory()->create()->id;
    }
}
