<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WorkOrder entity (spec 0093): "Commessa", a domain object with its own
 * sequential numbering (`code`, `COM-0001`, D-1), belonging to exactly ONE
 * Quote (`quote_id`, restrictOnDelete — D-5: an offer with at least one
 * WorkOrder is not deletable, guarded in QuoteService::delete()) and
 * IMMUTABLE after creation (enforced at the request layer via `prohibited`,
 * not here).
 *
 * "Contratto n." is DELIBERATELY not a column here (D-2): it is `quote.code`,
 * read through the `quote()` relation, never copied — same rule already
 * documented on `contracts`.
 *
 * `type` is a plain string cast to `App\Enums\WorkOrderType` (D-10), never a
 * shared enum. `is_force_closed`/`force_close_reason` (D-4) are the ONLY
 * writable "state" the client controls: the working status itself is
 * ALWAYS computed in read (`App\Services\WorkOrders\WorkOrderStatusResolver`,
 * D-3), never persisted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->foreignId('quote_id')->constrained('quotes')->restrictOnDelete();
            $table->string('title', 191)->index();
            $table->string('type', 16);
            $table->date('callback_date')->nullable()->index();
            $table->text('description')->nullable();
            $table->text('internal_notes')->nullable();
            $table->boolean('is_force_closed')->default(false)->index();
            $table->text('force_close_reason')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_orders');
    }
};
