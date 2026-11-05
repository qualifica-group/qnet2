<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Chart of accounts, phase 2 (spec 0350, D-9a): flags the seeded "Provvigioni
 * da enti" sub-account as the institution-commission default and classifies
 * the still unclassified lines of the invoices issued from an institution
 * proforma request. Products and categories carry no sub-account yet, so these
 * are the only lines the rule can classify. Plain DB facade: no model, so the
 * migration keeps working when the models change. Idempotent: only NULL lines
 * are touched.
 */
return new class extends Migration
{
    private const string DEFAULT_CODE = '70.03.001';

    public function up(): void
    {
        $default = DB::table('revenue_types')->where('code', self::DEFAULT_CODE)->value('id');

        if ($default === null) {
            return;
        }

        DB::table('revenue_types')->where('id', $default)->update(['is_institution_commission_default' => true]);

        DB::table('invoice_lines')
            ->whereNull('revenue_type_id')
            ->whereIn('invoice_id', $this->institutionInvoiceIds())
            ->update(['revenue_type_id' => $default]);
    }

    public function down(): void
    {
        $default = DB::table('revenue_types')->where('code', self::DEFAULT_CODE)->value('id');

        if ($default === null) {
            return;
        }

        DB::table('invoice_lines')
            ->where('revenue_type_id', $default)
            ->whereIn('invoice_id', $this->institutionInvoiceIds())
            ->update(['revenue_type_id' => null]);

        DB::table('revenue_types')->where('id', $default)->update(['is_institution_commission_default' => false]);
    }

    private function institutionInvoiceIds(): Builder
    {
        return DB::table('invoices')
            ->select('id')
            ->whereIn('proforma_request_id', DB::table('proforma_requests')->select('id')->where('kind', 'institution'));
    }
};
