<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Enums\ChartAccountKind;
use App\Enums\CostTypeNature;
use App\Exports\ChartOfAccounts\ChartOfAccountsLayout as Layout;
use App\Http\Requests\ChartOfAccounts\StoreChartAccountRequest;
use App\Rules\UniqueChartCode;
use App\Support\BadgeTokens;

/**
 * Syntax-level validation of one row (spec 0349, D-7): required fields,
 * allowed values, column limits and which columns the level may fill. What
 * depends on other rows or on the current chart is the planner's job.
 */
final class ChartRowParser
{
    public function parse(ImportRow $row, ImportErrors $errors): ParsedRow
    {
        $cells = $row->cells;
        $n = $row->number;
        $before = $errors->count();

        // Step 1: level decides which columns apply
        $level = $this->choice($errors, $n, 'level', Layout::LEVELS, $cells['level'], true);
        $isGroup = $level === null || $level === Layout::LEVEL_GROUP;
        $isSubAccount = $level === null || $level === Layout::LEVEL_SUB_ACCOUNT;

        // Step 2: identity and texts
        $code = $this->code($errors, $n, $cells['code']);
        $name = $this->text($errors, $n, 'name', $cells['name'], StoreChartAccountRequest::NAME_MAX, true);
        $description = $this->text($errors, $n, 'description', $cells['description'], StoreChartAccountRequest::DESCRIPTION_MAX, false);
        $parentCode = $this->parentCode($errors, $n, $level, $cells['parent_code']);

        // Step 3: kind / nature / color, only where the level allows them
        $kind = $this->choice($errors, $n, 'kind', Layout::KINDS, $cells['kind'], $level === Layout::LEVEL_GROUP);
        $this->forbidUnless($errors, $n, 'kind', $cells['kind'], $isGroup);
        $nature = $this->choice($errors, $n, 'nature', Layout::NATURES, $cells['nature'], false);
        $this->forbidUnless($errors, $n, 'nature', $cells['nature'], $isSubAccount);
        $color = $this->color($errors, $n, $cells['color']);
        $this->forbidUnless($errors, $n, 'color', $cells['color'], $isSubAccount);

        // Step 4: active flag (empty = active)
        $isActive = $this->isActive($errors, $n, $cells['active']);

        return new ParsedRow(
            number: $n,
            level: $level,
            code: $code ?? '',
            parentCode: $parentCode,
            name: $name ?? '',
            description: $description,
            kind: $kind === null ? null : ChartAccountKind::from($kind),
            nature: $nature === null ? null : CostTypeNature::from($nature),
            color: $color,
            isActive: $isActive,
            isValid: $errors->count() === $before,
        );
    }

    private function choice(ImportErrors $errors, int $row, string $column, string $group, string $raw, bool $required): ?string
    {
        if ($raw === '') {
            if ($required) {
                $errors->add($row, $column, 'required');
            }

            return null;
        }

        $value = Layout::parseValue($group, $raw);
        if ($value === null) {
            $errors->add($row, $column, 'invalid_value');
        }

        return $value;
    }

    private function code(ImportErrors $errors, int $row, string $raw): ?string
    {
        if ($raw === '') {
            $errors->add($row, 'code', 'required');

            return null;
        }

        if (mb_strlen($raw) > UniqueChartCode::MAX_LENGTH) {
            $errors->add($row, 'code', 'too_long', ['max' => UniqueChartCode::MAX_LENGTH]);

            return null;
        }

        if (preg_match(UniqueChartCode::FORMAT, $raw) !== 1) {
            $errors->add($row, 'code', 'code_format');

            return null;
        }

        return $raw;
    }

    private function text(ImportErrors $errors, int $row, string $column, string $raw, int $max, bool $required): ?string
    {
        if ($raw === '') {
            if ($required) {
                $errors->add($row, $column, 'required');
            }

            return null;
        }

        if (mb_strlen($raw) > $max) {
            $errors->add($row, $column, 'too_long', ['max' => $max]);

            return null;
        }

        return $raw;
    }

    private function parentCode(ImportErrors $errors, int $row, ?string $level, string $raw): ?string
    {
        if ($level === Layout::LEVEL_GROUP) {
            if ($raw !== '') {
                $errors->add($row, 'parent_code', 'parent_not_allowed');
            }

            return null;
        }

        if ($raw === '' && $level !== null) {
            $errors->add($row, 'parent_code', 'required');
        }

        return $raw === '' ? null : $raw;
    }

    private function color(ImportErrors $errors, int $row, string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }

        $color = mb_strtolower($raw);
        if (! in_array($color, BadgeTokens::colors(), true)) {
            $errors->add($row, 'color', 'invalid_value');

            return null;
        }

        return $color;
    }

    private function isActive(ImportErrors $errors, int $row, string $raw): bool
    {
        if ($raw === '') {
            return true;
        }

        $value = Layout::parseBoolean($raw);
        if ($value === null) {
            $errors->add($row, 'active', 'invalid_value');

            return true;
        }

        return $value;
    }

    private function forbidUnless(ImportErrors $errors, int $row, string $column, string $raw, bool $allowed): void
    {
        if (! $allowed && $raw !== '') {
            $errors->add($row, $column, 'not_allowed_here');
        }
    }
}
