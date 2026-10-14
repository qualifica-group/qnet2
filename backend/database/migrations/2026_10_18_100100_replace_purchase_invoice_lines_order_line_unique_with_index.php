<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An order line can be billed by several document lines (spec 0254, D-2, which
 * replaces the single billing of spec 0250 D-8). In MySQL the foreign key
 * relies on the unique index, so the plain index is created first.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->index('purchase_order_line_id');
        });
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->dropUnique(['purchase_order_line_id']);
        });
    }

    public function down(): void
    {
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->unique('purchase_order_line_id');
        });
        Schema::table('purchase_invoice_lines', function (Blueprint $table) {
            $table->dropIndex(['purchase_order_line_id']);
        });
    }
};
