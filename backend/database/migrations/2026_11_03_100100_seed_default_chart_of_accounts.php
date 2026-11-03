<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Starting structure of the managerial chart of accounts (spec 0348, D-11):
 * reference data also needed in production, so it is a data migration (same
 * precedent as the initial cost types of 2026_10_13_100000), not a seeder.
 * Idempotent: a code already present is skipped, never duplicated. Plain DB
 * facade, no model, so the migration does not depend on future model changes.
 */
return new class extends Migration
{
    /** @var array<string, array{name: string, kind: string, accounts: array<string, string>}> */
    private const array GROUPS = [
        '60' => ['name' => 'Acquisti di beni', 'kind' => 'cost', 'accounts' => [
            '60.01' => 'Materie prime e sussidiarie', '60.02' => 'Materiale di consumo', '60.03' => 'Merci',
        ]],
        '61' => ['name' => 'Servizi', 'kind' => 'cost', 'accounts' => [
            '61.01' => 'Consulenze e collaborazioni', '61.02' => 'Provvigioni', '61.03' => 'Utenze',
            '61.04' => 'Manutenzioni', '61.05' => 'Viaggi e trasferte', '61.06' => 'Pubblicità e marketing',
            '61.07' => 'Altri servizi',
        ]],
        '62' => ['name' => 'Godimento beni di terzi', 'kind' => 'cost', 'accounts' => [
            '62.01' => 'Affitti e locazioni', '62.02' => 'Noleggi e leasing',
        ]],
        '63' => ['name' => 'Personale', 'kind' => 'cost', 'accounts' => [
            '63.01' => 'Retribuzioni', '63.02' => 'Oneri sociali', '63.03' => 'Trattamento di fine rapporto',
        ]],
        '64' => ['name' => 'Ammortamenti', 'kind' => 'cost', 'accounts' => [
            '64.01' => 'Immobilizzazioni immateriali', '64.02' => 'Immobilizzazioni materiali',
        ]],
        '65' => ['name' => 'Oneri diversi di gestione', 'kind' => 'cost', 'accounts' => [
            '65.01' => 'Imposte e tasse non sul reddito', '65.02' => 'Altri oneri',
        ]],
        '66' => ['name' => 'Oneri finanziari', 'kind' => 'cost', 'accounts' => [
            '66.01' => 'Interessi passivi', '66.02' => 'Commissioni e spese bancarie',
        ]],
        '67' => ['name' => 'Imposte sul reddito', 'kind' => 'cost', 'accounts' => [
            '67.01' => 'IRES', '67.02' => 'IRAP',
        ]],
        '70' => ['name' => 'Ricavi delle vendite e delle prestazioni', 'kind' => 'revenue', 'accounts' => [
            '70.01' => 'Prestazioni di servizi', '70.02' => 'Vendita di prodotti', '70.03' => 'Provvigioni attive',
        ]],
        '71' => ['name' => 'Altri ricavi e proventi', 'kind' => 'revenue', 'accounts' => [
            '71.01' => 'Contributi', '71.02' => 'Altri proventi',
        ]],
        '72' => ['name' => 'Proventi finanziari', 'kind' => 'revenue', 'accounts' => [
            '72.01' => 'Interessi attivi',
        ]],
    ];

    /** @var array<string, array{account: string, name: string}> revenue sub-account code => parent account + name */
    private const array REVENUE_TYPES = [
        '70.01.001' => ['account' => '70.01', 'name' => 'Ricavi da consulenza'],
        '70.02.001' => ['account' => '70.02', 'name' => 'Ricavi da vendita prodotti'],
        '70.03.001' => ['account' => '70.03', 'name' => 'Provvigioni da enti'],
    ];

    /** @var array<string, array{account: string, code: string}> initial cost type name (lowercase) => placement */
    private const array COST_TYPE_PLACEMENT = [
        'collaboratori' => ['account' => '61.01', 'code' => '61.01.001'],
        'utenze' => ['account' => '61.03', 'code' => '61.03.001'],
        'fitti' => ['account' => '62.01', 'code' => '62.01.001'],
        'altro' => ['account' => '65.02', 'code' => '65.02.001'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::GROUPS as $groupCode => $group) {
            $groupId = $this->ensureAccount('group', null, $group['kind'], $groupCode, $group['name'], $now);

            foreach ($group['accounts'] as $accountCode => $accountName) {
                $this->ensureAccount('account', $groupId, null, $accountCode, $accountName, $now);
            }
        }

        $this->seedRevenueTypes($now);
        $this->placeInitialCostTypes();
    }

    public function down(): void
    {
        DB::table('cost_types')
            ->whereIn('code', array_column(self::COST_TYPE_PLACEMENT, 'code'))
            ->update(['chart_account_id' => null, 'code' => null]);

        DB::table('revenue_types')->whereIn('code', array_keys(self::REVENUE_TYPES))->delete();

        $accountCodes = array_merge(...array_map(
            static fn (array $group): array => array_keys($group['accounts']),
            array_values(self::GROUPS),
        ));

        DB::table('chart_accounts')->whereIn('code', $accountCodes)->delete();
        DB::table('chart_accounts')->whereIn('code', array_keys(self::GROUPS))->delete();
    }

    private function ensureAccount(string $level, ?int $parentId, ?string $kind, string $code, string $name, mixed $now): int
    {
        $existing = DB::table('chart_accounts')->where('code', $code)->value('id');

        if ($existing !== null) {
            return (int) $existing;
        }

        return (int) DB::table('chart_accounts')->insertGetId([
            'level' => $level,
            'parent_id' => $parentId,
            'kind' => $kind,
            'code' => $code,
            'name' => $name,
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function seedRevenueTypes(mixed $now): void
    {
        foreach (self::REVENUE_TYPES as $code => $type) {
            if (DB::table('revenue_types')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('revenue_types')->insert([
                'chart_account_id' => $this->accountId($type['account']),
                'code' => $code,
                'name' => $type['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /** Places the initial cost types by name (trim, case-insensitive), only when they have no account yet. */
    private function placeInitialCostTypes(): void
    {
        $unplaced = DB::table('cost_types')->whereNull('chart_account_id')->get(['id', 'name']);

        foreach ($unplaced as $costType) {
            $placement = self::COST_TYPE_PLACEMENT[mb_strtolower(trim((string) $costType->name))] ?? null;

            if ($placement === null || DB::table('cost_types')->where('code', $placement['code'])->exists()) {
                continue;
            }

            DB::table('cost_types')->where('id', $costType->id)->update([
                'chart_account_id' => $this->accountId($placement['account']),
                'code' => $placement['code'],
            ]);
        }
    }

    private function accountId(string $code): int
    {
        return (int) DB::table('chart_accounts')->where('code', $code)->value('id');
    }
};
