<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-row ArchivioSmart connection (spec 0403, D-2). The token column holds
 * the encrypted value (model cast), hence text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivio_smart_settings', function (Blueprint $table) {
            $table->id();
            $table->string('base_url', 255)->nullable();
            $table->text('token')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivio_smart_settings');
    }
};
