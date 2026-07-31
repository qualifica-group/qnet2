<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contract entity (spec 0072): the additional lifecycle data for a Quote that
 * has reached a `closed_won` status, one-to-one with `quotes`. NO column here
 * duplicates the quote (D-1): there is no `code` — the "codice" shown to the
 * user is always `quotes.code` (`QUO-0001`), read through the `quote()`
 * relation, never copied. Client, opportunity, products, amounts and
 * commissions all stay exclusively on `quotes`/`quote_lines`/`opportunities`.
 *
 * `quote_id` is UNIQUE (one contract per quote) and `cascadeOnDelete` (D-6):
 * a contract is never created or deleted by hand, only by the automation on
 * the quote's status transition, and it lives exactly as long as the quote
 * does — deleting the quote is the only way a contract row disappears.
 *
 * `contract_status_id` is mandatory and `restrictOnDelete` (a status still
 * referenced by a contract cannot be removed), matching `quotes.
 * quote_status_id`. `validated_by`/`terminated_by` are `nullOnDelete`: losing
 * the referenced User must not take down contract history.
 *
 * `suspended_at`/`status_before_suspension_id` (D-3) back the automatic
 * suspend/reactivate cycle when the quote leaves/re-enters `closed_won`:
 * `status_before_suspension_id` is `nullOnDelete` — the same defense-in-depth
 * choice as the layout/company FKs elsewhere, the real guard being the
 * application-level `ContractLifecycleManager` (spec 0072 BR-1/BR-2).
 *
 * `accepted_at`/`validated_at`/`terminated_at` are plain nullable dates,
 * written exactly once each by the domain actions (BR-3/BR-4) or the creation
 * automation (BR-1, D-7) — never touched by mass assignment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->unique()->constrained('quotes')->cascadeOnDelete();
            $table->foreignId('contract_status_id')->constrained('contract_statuses')->restrictOnDelete();
            $table->date('accepted_at')->nullable();
            $table->date('validated_at')->nullable();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('renewal_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->date('terminated_at')->nullable();
            $table->foreignId('terminated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('termination_reason')->nullable();
            $table->text('payment_notes')->nullable();
            $table->text('comments')->nullable();
            $table->dateTime('suspended_at')->nullable();
            $table->foreignId('status_before_suspension_id')->nullable()->constrained('contract_statuses')->nullOnDelete();
            $table->timestamps();

            $table->index('expiry_date');
            $table->index('renewal_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
