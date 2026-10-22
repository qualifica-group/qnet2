<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0304 D-8/D-10/D-15: half-day part, short-notice flag fixed at
 * submission and the closure that generated the request. Deleting generated
 * requests goes through the closure service, hence restrictOnDelete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->string('day_part', 10)->nullable();
            $table->boolean('is_short_notice')->default(false);
            $table->foreignId('company_closure_id')->nullable()->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('absence_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('company_closure_id');
            $table->dropColumn(['day_part', 'is_short_notice']);
        });
    }
};
