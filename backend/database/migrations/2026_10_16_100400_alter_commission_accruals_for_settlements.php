<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ledger extensions for settlements (spec 0213): company (backfilled from the
 * invoice), the settlement a movement belongs to, manual adjustment notes and
 * author; recipient_role becomes nullable because ADJUSTMENT rows have none.
 * Table changes run without FK checks: SQLite rebuilds the table on ->change()
 * and dropping the old copy would otherwise cascade. down() deletes the
 * ADJUSTMENT rows first, since they cannot satisfy the NOT NULL role.
 */
return new class extends Migration
{
    private const OPEN_INDEX = 'commission_accruals_open_index';

    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('invoice_installment_id')->constrained('companies')->restrictOnDelete();
                $table->foreignId('commission_settlement_id')->nullable()->after('company_id')->constrained('commission_settlements')->restrictOnDelete();
                $table->string('notes', 500)->nullable()->after('settled_externally_on');
                $table->foreignId('created_by')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            });

            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->string('recipient_role', 32)->nullable()->change();
            });

            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->index(['recipient_type', 'recipient_id', 'company_id', 'commission_settlement_id'], self::OPEN_INDEX);
            });
        });

        DB::statement('UPDATE commission_accruals SET company_id = (SELECT invoices.company_id FROM invoices WHERE invoices.id = commission_accruals.invoice_id) WHERE invoice_id IS NOT NULL');
    }

    public function down(): void
    {
        DB::table('commission_accruals')->where('type', 'ADJUSTMENT')->delete();

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->dropIndex(self::OPEN_INDEX);
            });

            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->dropConstrainedForeignId('created_by');
                $table->dropConstrainedForeignId('commission_settlement_id');
                $table->dropConstrainedForeignId('company_id');
                $table->dropColumn('notes');
            });

            Schema::table('commission_accruals', function (Blueprint $table) {
                $table->string('recipient_role', 32)->nullable(false)->change();
            });
        });
    }
};
