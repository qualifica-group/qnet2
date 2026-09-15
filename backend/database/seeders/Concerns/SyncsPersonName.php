<?php

namespace Database\Seeders\Concerns;

use App\Enums\PersonalDataTypeEnum;
use App\Models\User;

/**
 * Writes a seeded account's first and last name onto its personal-data card
 * (the user's anagrafica), for the seeders of the real accounts (user directive
 * 2026-09-15). Convergent: the card is upserted, so a re-run rewrites the names
 * without duplicating it; every other field of the card is left untouched.
 */
trait SyncsPersonName
{
    protected function syncPersonName(User $user, string $firstName, string $lastName): void
    {
        $user->personalData()->updateOrCreate([], [
            'type' => PersonalDataTypeEnum::Individual,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }
}
