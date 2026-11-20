<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Real Formalab data (2026-11-20, 1395 classes) has codes up to 210 and
 * "code - title" labels up to 399 characters: past the default 255.
 */
return new class extends Migration
{
    private const int TEXT_LENGTH = 512;

    public function up(): void
    {
        Schema::table('formalab_classes', function (Blueprint $table): void {
            $table->string('code', self::TEXT_LENGTH)->change();
            $table->string('title', self::TEXT_LENGTH)->change();
            $table->string('label', self::TEXT_LENGTH)->change();
        });
    }

    public function down(): void
    {
        Schema::table('formalab_classes', function (Blueprint $table): void {
            $table->string('code')->change();
            $table->string('title')->change();
            $table->string('label')->change();
        });
    }
};
