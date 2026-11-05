<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Starting financial group of the chart of accounts (spec 0360, D-3): group 90
 * with its accounts and one sub-account each. Reference data also needed in
 * production, so a data migration like 2026_11_03_100100. Idempotent: a code
 * already present is skipped. Plain DB facade, no model.
 */
return new class extends Migration
{
    private const string GROUP_CODE = '90';

    /** @var array<string, string> account code => name */
    private const array ACCOUNTS = [
        '90.01' => 'Giroconti',
        '90.02' => 'Finanziamenti',
        '90.03' => 'Tributi e contributi',
        '90.04' => 'Rettifiche di saldo',
    ];

    /** @var array<string, array{account: string, name: string}> sub-account code => parent account + name */
    private const array TREASURY_TYPES = [
        '90.01.001' => ['account' => '90.01', 'name' => 'Giroconto tra conti'],
        '90.02.001' => ['account' => '90.02', 'name' => 'Finanziamenti e rimborsi'],
        '90.03.001' => ['account' => '90.03', 'name' => 'Tributi e contributi (F24)'],
        '90.04.001' => ['account' => '90.04', 'name' => 'Rettifica di saldo'],
    ];

    public function up(): void
    {
        $now = now();

        $groupId = $this->ensureAccount('group', null, 'financial', self::GROUP_CODE, 'Movimenti finanziari', $now);

        foreach (self::ACCOUNTS as $code => $name) {
            $this->ensureAccount('account', $groupId, null, $code, $name, $now);
        }

        foreach (self::TREASURY_TYPES as $code => $type) {
            if (DB::table('treasury_types')->where('code', $code)->exists()) {
                continue;
            }

            DB::table('treasury_types')->insert([
                'chart_account_id' => $this->accountId($type['account']),
                'code' => $code,
                'name' => $type['name'],
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('treasury_types')->whereIn('code', array_keys(self::TREASURY_TYPES))->delete();
        DB::table('chart_accounts')->whereIn('code', array_keys(self::ACCOUNTS))->delete();
        DB::table('chart_accounts')->where('code', self::GROUP_CODE)->delete();
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

    private function accountId(string $code): int
    {
        return (int) DB::table('chart_accounts')->where('code', $code)->value('id');
    }
};
