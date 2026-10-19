<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0276 D-1: the "Responsabile Si/No" flag of the employment profile is
 * gone; the only manager concept left is `business_functions.manager_id`.
 * The column is dropped and the `users` field-permission rows on
 * `employment.is_manager` are deleted so the role matrix keeps no orphan
 * configuration.
 *
 * down() recreates the column (default false) but cannot restore the
 * deleted permission rows nor the old values.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('role_field_permissions')
            ->where('resource', 'users')
            ->where('field', 'employment.is_manager')
            ->delete();

        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->dropColumn('is_manager');
        });
    }

    public function down(): void
    {
        Schema::table('employment_profiles', function (Blueprint $table): void {
            $table->boolean('is_manager')->default(false)->after('user_id');
        });
    }
};
