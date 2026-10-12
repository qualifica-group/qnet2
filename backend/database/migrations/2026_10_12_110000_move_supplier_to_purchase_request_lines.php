<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Purchase requests (spec 0209, D-5): the supplier moves from the header to the
 * lines. The header supplier is copied onto every line of its request, the
 * field-permission rows of the dropped header field are removed, then the header
 * column goes. down() restores the header column from the first line (by
 * position) that has a supplier. Header table changes run without FK checks:
 * SQLite rebuilds the table to add/drop a constrained column, and the DROP of
 * the old copy would otherwise cascade-delete every line.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('description')->constrained('registries')->nullOnDelete();
        });

        DB::statement('UPDATE purchase_request_lines SET supplier_id = (SELECT purchase_requests.supplier_id FROM purchase_requests WHERE purchase_requests.id = purchase_request_lines.purchase_request_id)');

        DB::table('role_field_permissions')->where('resource', 'purchase-requests')->where('field', 'supplier_id')->delete();

        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        }));
    }

    public function down(): void
    {
        Schema::withoutForeignKeyConstraints(fn () => Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained('registries')->nullOnDelete();
        }));

        DB::statement('UPDATE purchase_requests SET supplier_id = (SELECT l.supplier_id FROM purchase_request_lines l WHERE l.purchase_request_id = purchase_requests.id AND l.supplier_id IS NOT NULL ORDER BY l.position, l.id LIMIT 1)');

        Schema::table('purchase_request_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
    }
};
