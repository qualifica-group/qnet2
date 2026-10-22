<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0304 D-9..D-12: allowed modes, minimum notice, badge color and default
 * yearly allowance (days) of an absence type. Defaults keep existing rows as
 * they were: every mode allowed, no notice, slate, no default allowance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_types', function (Blueprint $table): void {
            $table->boolean('allows_full_day')->default(true);
            $table->boolean('allows_half_day')->default(true);
            $table->boolean('allows_hourly')->default(true);
            $table->unsignedSmallInteger('min_notice_days')->nullable();
            $table->string('color', 20)->default('slate');
            $table->decimal('default_annual_days', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('absence_types', function (Blueprint $table): void {
            $table->dropColumn([
                'allows_full_day', 'allows_half_day', 'allows_hourly', 'min_notice_days', 'color', 'default_annual_days',
            ]);
        });
    }
};
