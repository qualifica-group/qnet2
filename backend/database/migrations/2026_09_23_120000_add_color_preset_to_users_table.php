<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Per-user color preset (accent palette of the UI). Null means "no
            // preference yet" — the resource layer serializes 'default' instead,
            // same discipline as `ui_scale`/`date_format`.
            $table->string('color_preset', 16)->nullable()->after('time_format');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('color_preset');
        });
    }
};
