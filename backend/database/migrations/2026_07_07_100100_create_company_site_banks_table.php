<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank accounts owned by a company site (spec 0020), a real 1→N (FK
 * `company_site_id`, not a morph) unlike the polymorphic address/logo — see
 * BankService for the diff-by-id sync invariant. Cascades on the site's
 * delete.
 *
 * `is_primary` carries the "preferred bank" concept, mirroring the
 * single-primary invariant already used by contacts/addresses (at most one
 * primary per owner, enforced in BankService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_site_banks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('old_id')->nullable();
            $table->unique('old_id');

            $table->foreignId('company_site_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('iban', 50)->nullable();
            $table->string('notes', 191)->nullable();
            $table->boolean('is_primary')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_site_banks');
    }
};
