<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `layout_id` on an Invoice (spec 0196, D-1): the Document Layout the PDF is
 * generated against. NULL means "use the invoices default at print time".
 * `restrictOnDelete`: a layout in use is protected, the friendly guard lives
 * in DocumentLayoutService::assertNotInUse. `constrained()` also indexes it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('layout_id')
                ->nullable()
                ->constrained('document_layouts')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('layout_id');
        });
    }
};
