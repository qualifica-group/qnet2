<?php

declare(strict_types=1);

use App\Models\AbsenceRequest;
use App\Models\AbsenceType;
use App\Models\BusinessFunction;
use App\Models\User;

if (! function_exists('insightPerson')) {
    function insightPerson(): User
    {
        $person = absenceUserWith();
        absenceProfile($person, absenceUserWith());

        return $person;
    }
}

if (! function_exists('insightAbsence')) {
    /** @param array<string, mixed> $attributes */
    function insightAbsence(User $person, string $state, array $attributes = []): AbsenceRequest
    {
        return AbsenceRequest::factory()->forUser($person)->{$state}()->create([
            'absence_type_id' => AbsenceType::factory()->create(['color' => 'blue'])->id,
            'starts_on' => '2026-12-14',
            'ends_on' => '2026-12-14',
            ...$attributes,
        ]);
    }
}

if (! function_exists('insightFunction')) {
    function insightFunction(User ...$members): BusinessFunction
    {
        $function = BusinessFunction::factory()->create();

        foreach ($members as $member) {
            $member->businessFunctions()->attach($function->id);
        }

        return $function;
    }
}
