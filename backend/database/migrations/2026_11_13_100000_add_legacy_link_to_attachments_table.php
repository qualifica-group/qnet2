<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link-only legacy documents (spec 0295 D-10): a migrated document keeps its
 * file on the legacy QNet and is reached through `external_url`; no binary is
 * stored locally. The legacy document tables overlap their ids, so the
 * migration anchor is the pair (`old_source`, `old_id`), as on invoices.
 */
return new class extends Migration
{
    private const string UNIQUE = 'attachments_old_source_old_id_unique';

    public function up(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->string('old_source', 64)->nullable()->after('id');
            $table->unsignedBigInteger('old_id')->nullable()->after('old_source');
            $table->string('external_url', 2048)->nullable()->after('path');
            $table->unique(['old_source', 'old_id'], self::UNIQUE);
        });
    }

    public function down(): void
    {
        Schema::table('attachments', function (Blueprint $table) {
            $table->dropUnique(self::UNIQUE);
            $table->dropColumn(['old_source', 'old_id', 'external_url']);
        });
    }
};
