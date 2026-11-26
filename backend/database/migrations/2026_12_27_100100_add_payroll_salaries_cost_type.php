<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The cost type of the payslip lines (spec 0431, D-6): "Retribuzioni al
 * personale" under the chart account 63.01 "Retribuzioni" when it exists.
 * An existing cost type with the same code is reused and never removed.
 */
return new class extends Migration
{
    private const string CODE = '63.01.001';

    private const string NAME = 'Retribuzioni al personale';

    private const string CHART_ACCOUNT_CODE = '63.01';

    public function up(): void
    {
        if (DB::table('cost_types')->where('code', self::CODE)->orWhere('name', self::NAME)->exists()) {
            return;
        }

        DB::table('cost_types')->insert([
            'chart_account_id' => DB::table('chart_accounts')->where('code', self::CHART_ACCOUNT_CODE)->value('id'),
            'code' => self::CODE,
            'name' => self::NAME,
            'nature' => 'general',
            'color' => 'slate',
            'sort_order' => ((int) DB::table('cost_types')->max('sort_order')) + 1,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        $id = DB::table('cost_types')->where('code', self::CODE)->value('id');

        if ($id === null || DB::table('purchase_invoice_lines')->where('cost_type_id', $id)->exists()) {
            return;
        }

        DB::table('cost_types')->where('id', $id)->delete();
    }
};
