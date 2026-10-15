<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0267, D-4: a NULL `work_orders.quote_id` marks a multi-offer commessa
 * (its source offers are derived from the `quote_line_work_order` pivot). The
 * FK (restrictOnDelete) and its index are kept; only nullability changes.
 *
 * down() refuses to run while multi-offer rows exist: restoring NOT NULL would
 * otherwise fail opaquely, and deleting or guessing an offer is never acceptable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        $count = DB::table('work_orders')->whereNull('quote_id')->count();

        if ($count > 0) {
            throw new RuntimeException(
                "Cannot make work_orders.quote_id NOT NULL: {$count} multi-offer work order(s) have a NULL quote_id. ".
                'Remove or convert them to a single offer before rolling back this migration.'
            );
        }

        Schema::table('work_orders', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable(false)->change();
        });
    }
};
