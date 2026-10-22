<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Spec 0304 D-13: company closures (holidays and collective leave), valid for
 * all operational sites or for the chosen ones (pivot).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_closures', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('kind', 20);
            $table->foreignId('absence_type_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('applies_to_all_sites');
            $table->boolean('repeats_yearly')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['starts_on', 'ends_on']);
        });

        Schema::create('company_closure_operational_site', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_closure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operational_site_id')->constrained()->cascadeOnDelete();

            $table->unique(['company_closure_id', 'operational_site_id'], 'company_closure_site_unique');
            $table->index('operational_site_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_closure_operational_site');
        Schema::dropIfExists('company_closures');
    }
};
