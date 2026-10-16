<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0267: `DOCUMENT_RECEIVED` (17 characters) does not fit the string(16)
 * status column of spec 0213, so MySQL rejects the transition (SQLite does not
 * enforce the length, which is why the suite did not catch it). The column
 * grows to 32.
 *
 * down() moves DOCUMENT_RECEIVED rows back to ISSUED before shrinking, the
 * same state the document migration's own down() leaves them in once their
 * document columns are dropped.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_settlements', function (Blueprint $table): void {
            $table->string('status', 32)->change();
        });
    }

    public function down(): void
    {
        DB::table('commission_settlements')
            ->where('status', 'DOCUMENT_RECEIVED')
            ->update(['status' => 'ISSUED']);

        Schema::table('commission_settlements', function (Blueprint $table): void {
            $table->string('status', 16)->change();
        });
    }
};
