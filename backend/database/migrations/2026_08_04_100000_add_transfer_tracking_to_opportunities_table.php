<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backs the "Trasferimento contatto tra sedi operative" tracking (spec 0079):
 * `is_transferred` (a real, sortable/filterable/exportable boolean) and
 * `transferred_from_operational_site_id` (the ORIGIN Sede, nullOnDelete)
 * answer two DIFFERENT questions — "was this ever transferred?" and "from
 * where?" — and are NOT derivable from one another: a request with no Sede
 * of origin can still be transferred, leaving the origin null while
 * `is_transferred` stays true (spec data_contract). `nullOnDelete`: losing
 * the referenced Sede clears the origin (and the detail-panel notice) but
 * never lowers `is_transferred`.
 *
 * Neither column is in Opportunity::$fillable (see the model): both are
 * system flags, written exclusively by RequestTransferService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->boolean('is_transferred')->default(false)->after('operational_site_id');
            $table->foreignId('transferred_from_operational_site_id')->nullable()
                ->after('is_transferred')->constrained('operational_sites')->nullOnDelete();
            $table->index('is_transferred');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->dropForeign(['transferred_from_operational_site_id']);
            $table->dropIndex(['is_transferred']);
            $table->dropColumn(['is_transferred', 'transferred_from_operational_site_id']);
        });
    }
};
