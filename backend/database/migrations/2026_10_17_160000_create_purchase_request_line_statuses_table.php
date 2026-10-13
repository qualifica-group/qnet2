<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Presentation catalog of the purchase request line statuses (spec 0248, D-1):
 * one row per case of PurchaseRequestLineStatus (`system_key` = enum value),
 * carrying the editable name, badge color/icon and display order. The rows
 * are created here, with the Italian names, colors and icons the interface
 * used to hard-code, in the order of the frontend list; nothing creates or
 * deletes them afterwards.
 */
return new class extends Migration
{
    /** Enum value => [name, color, icon], in display order. */
    private const array REFERENCE_STATUSES = [
        'pending_approval' => ['Da approvare', 'slate', 'clock'],
        'approved' => ['Approvato', 'teal', 'check-circle-2'],
        'ordered' => ['Ordinato', 'blue', 'shopping-cart'],
        'received' => ['Ricevuto', 'indigo', 'package'],
        'rejected' => ['Rifiutato', 'red', 'lock'],
        'on_hold' => ['Stand by', 'gray', 'timer'],
    ];

    public function up(): void
    {
        Schema::create('purchase_request_line_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('system_key', 32)->unique();
            $table->string('name', 191)->unique();
            $table->string('color', 32);
            $table->string('icon', 64)->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $sortOrder = 0;

        foreach (self::REFERENCE_STATUSES as $systemKey => [$name, $color, $icon]) {
            DB::table('purchase_request_line_statuses')->insert([
                'system_key' => $systemKey,
                'name' => $name,
                'color' => $color,
                'icon' => $icon,
                'sort_order' => ++$sortOrder * 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_line_statuses');
    }
};
