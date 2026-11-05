<?php

declare(strict_types=1);

namespace App\Services\ChartOfAccounts;

use App\Models\TreasuryType;

/**
 * Wire shape of a financial sub-account reference (spec 0355, data_contract):
 * { id, code, name, path: "70 Group > 70.01 Account", is_active }. Lazy loading
 * is forbidden, so callers eager-load {@see self::RELATION} first.
 */
final class TreasuryTypeRef
{
    /** Relation path the path label reads (sub-account > account > group). */
    public const string RELATION = 'chartAccount.parent';

    /**
     * @return array{id: int, code: string, name: string, path: string, is_active: bool}
     */
    public static function from(TreasuryType $type): array
    {
        $account = $type->chartAccount;
        $group = $account->parent;

        return [
            'id' => $type->id,
            'code' => $type->code,
            'name' => $type->name,
            'path' => "{$group->code} {$group->name} > {$account->code} {$account->name}",
            'is_active' => $type->is_active,
        ];
    }

    /**
     * @return array{id: int, code: string, name: string, path: string, is_active: bool}|null
     */
    public static function find(?int $id): ?array
    {
        if ($id === null) {
            return null;
        }

        $type = TreasuryType::query()->with(self::RELATION)->find($id);

        return $type === null ? null : self::from($type);
    }
}
