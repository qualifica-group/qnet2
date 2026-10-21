<?php

namespace App\Enums;

use App\Enums\Attributes\IsDefault;
use App\Enums\Attributes\Label;
use App\Enums\Concerns\HasMeta;

/**
 * Contractual relationship a user's employment profile is bound by (spec
 * 0015). Single source of truth for the allowed values, shared by the model
 * cast, the nested `employment.relationship_type` validation and the grid
 * column's set-filter options/enumKey.
 */
enum RelationshipTypeEnum: string
{
    use HasMeta;

    #[Label('Employee')]
    #[IsDefault(true)]
    case Employee = 'employee';

    #[Label('Self-employed')]
    case SelfEmployed = 'self_employed';

    #[Label('Intern')]
    case Intern = 'intern';

    #[Label('Other')]
    case Other = 'other';

    /**
     * The supported values (the `value` of every case), for validation rules
     * and option lists.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
