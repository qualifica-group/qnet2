<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presentation catalogue of the purchase order (ODA) statuses (spec 0248, D-1):
 * one system row per PurchaseOrderStatus enum case, created here so it exists in
 * production and in tests. The enum keeps all the logic; the user may only
 * rename, recolour, re-icon and reorder the rows. `system_key` is the enum
 * value and never changes.
 */
return new class extends Migration
{
    /** Enum value => [name, color, icon, sort_order], in the order of the former frontend list. */
    private const array REFERENCE_STATUSES = [
        'draft' => ['Bozza', 'slate', 'pen-line', 1],
        'in_validation' => ['In validazione', 'amber', 'clock', 2],
        'approved' => ['Approvato', 'teal', 'check-circle-2', 3],
        'sent_to_supplier' => ['Inviato al fornitore', 'blue', 'send', 4],
        'partially_fulfilled' => ['Parzialmente evaso', 'indigo', 'package', 5],
        'fulfilled' => ['Evaso', 'green', 'badge-check', 6],
        'cancelled' => ['Annullato', 'red', 'lock', 7],
    ];

    public function up(): void
    {
        Schema::create('purchase_order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('system_key', 32)->unique();
            $table->string('name', 191)->unique();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('sort_order');
        });

        foreach (self::REFERENCE_STATUSES as $systemKey => [$name, $color, $icon, $sortOrder]) {
            DB::table('purchase_order_statuses')->insert([
                'system_key' => $systemKey,
                'name' => $name,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => $sortOrder,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_statuses');
    }
};
