<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0271 D-2: the free-text `job_description` is replaced by the job role
 * catalogue (`employment_profile_job_role`). Existing values are discarded by
 * explicit user decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn('job_description');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->string('job_description')->nullable();
        });
    }
};
