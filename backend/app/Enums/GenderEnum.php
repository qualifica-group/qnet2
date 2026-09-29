<?php

namespace App\Enums;

use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Biological sex of a natural-person personal-data card (type=individual):
 * male or female. Single source of truth for the allowed values, shared by the
 * model cast, the factory, the per-card validation and the client select.
 * Meaningless for a company card, where it stays null.
 */
enum GenderEnum: string
{
    use HasMeta;

    #[Label('Male')]
    case Male = 'male';

    #[Label('Female')]
    case Female = 'female';

    /**
     * Resolve a stored/raw value to a gender, defaulting to Male for anything
     * unknown or missing — so the domain never holds an out-of-contract value.
     */
    public static function fromValue(?string $value): self
    {
        return self::tryFrom($value ?? '') ?? self::Male;
    }
}
