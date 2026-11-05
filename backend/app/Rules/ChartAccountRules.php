<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\ChartAccountKind;
use App\Enums\ChartAccountLevel;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared tree-shape rules of the managerial chart of accounts (spec 0348, D-9).
 */
final class ChartAccountRules
{
    /**
     * An ACCOUNT (not a group) whose parent group is of the given kind: the only
     * valid parent of a cost or revenue sub-account.
     */
    public static function accountOfKind(ChartAccountKind $kind): Exists
    {
        return Rule::exists('chart_accounts', 'id')
            ->where('level', ChartAccountLevel::Account->value)
            ->using(static fn (Builder $query) => $query->whereIn(
                'parent_id',
                static fn (Builder $groups) => $groups->select('id')->from('chart_accounts')
                    ->where('level', ChartAccountLevel::Group->value)
                    ->where('kind', $kind->value),
            ));
    }
}
