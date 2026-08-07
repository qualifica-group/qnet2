<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrates "Trasferimento contatto tra sedi operative" tracking from the
 * Opportunity onto the Offerta (spec 0086, D-6): the flag becomes per-offer
 * rather than per-deal — two quotes of the same opportunity can now have a
 * different `is_transferred`/origin. Column shape copied verbatim from
 * `2026_08_04_100000_add_transfer_tracking_to_opportunities_table` (same
 * `nullOnDelete` guarantee: losing the referenced Sede clears the origin but
 * never lowers `is_transferred`).
 *
 * Neither column is in Quote::$fillable (see the model): both are system
 * flags, written exclusively by RequestTransferService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->boolean('is_transferred')->default(false)->after('operational_site_id');
            $table->foreignId('transferred_from_operational_site_id')->nullable()
                ->after('is_transferred')->constrained('operational_sites')->nullOnDelete();
            $table->index('is_transferred');
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table): void {
            $table->dropForeign(['transferred_from_operational_site_id']);
            $table->dropIndex(['is_transferred']);
            $table->dropColumn(['is_transferred', 'transferred_from_operational_site_id']);
        });
    }
};
