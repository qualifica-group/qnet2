<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user display preferences for every date/time the UI renders.
            // Null means "no preference yet" — the resource layer serializes the
            // defaults ('dmy', '24h') instead of null, so the client never
            // branches on null. Same discipline as `ui_scale`.
            $table->string('date_format', 8)->nullable()->after('ui_scale');
            $table->string('time_format', 8)->nullable()->after('date_format');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['date_format', 'time_format']);
        });
    }
};
