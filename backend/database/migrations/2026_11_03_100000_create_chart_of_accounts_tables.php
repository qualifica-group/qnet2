<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Managerial chart of accounts, phase 1 (spec 0348, D-3): groups and accounts
 * in `chart_accounts`, revenue sub-accounts in `revenue_types`; the existing
 * `cost_types` become the cost sub-accounts (parent account + code).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('level', 10);
            $table->foreignId('parent_id')->nullable()->constrained('chart_accounts')->restrictOnDelete();
            $table->string('kind', 10)->nullable();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('revenue_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chart_account_id')->constrained('chart_accounts')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('cost_types', function (Blueprint $table) {
            $table->foreignId('chart_account_id')->nullable()->after('id')->constrained('chart_accounts')->restrictOnDelete();
            $table->string('code', 20)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('cost_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chart_account_id');
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });

        Schema::dropIfExists('revenue_types');
        Schema::dropIfExists('chart_accounts');
    }
};
