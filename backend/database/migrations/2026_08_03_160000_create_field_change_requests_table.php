<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic "change request" entity (spec 0078): one row is ONE proposed
 * change to ONE protected field of ONE record, indexed by the
 * (resource, field) pair (F-6) rather than by a dedicated table per module.
 * First and only wired use case: `source_id` on `request-management`
 * (an `Opportunity`, F-1), addressed via `morphs('subject')` so the same
 * table serves any future protected field on any morph-mapped model.
 *
 * `pending_key` (D-5) is the portable substitute for a partial unique index
 * ("one pending request per record+field"): a `WHERE status = 'pending'`
 * partial/filtered unique index is a MySQL/Postgres feature with no SQLite
 * equivalent, and this project runs MySQL in production but SQLite in
 * dev/test (CLAUDE.md §0). Both drivers instead allow an unlimited number
 * of NULLs on a UNIQUE column, so the application layer writes
 * `"{subject_type}:{subject_id}:{field}"` into `pending_key` only while
 * `status = 'pending'` and nulls it out the moment the request is approved
 * or rejected — the UNIQUE constraint then only ever collides with another
 * still-pending request on the same record+field (AC-020/AC-021).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_change_requests', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 64);
            $table->morphs('subject');
            $table->string('field', 64);
            $table->json('current_value')->nullable();
            $table->json('requested_value')->nullable();
            $table->string('current_label', 191)->nullable();
            $table->string('requested_label', 191)->nullable();
            $table->text('reason')->nullable();
            $table->string('status', 16)->default('pending');
            $table->string('pending_key', 191)->nullable()->unique();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('handled_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('handling_note')->nullable();
            $table->timestamps();

            // `morphs('subject')` already adds an index on
            // (subject_type, subject_id) alone; this composite adds `status`
            // on top for the "pending requests on this record" lookup
            // (F-5 badge count, AC-037) without a second index scan.
            $table->index(['subject_type', 'subject_id', 'status']);
            // Dedicated tabular view filtered by module + status (F-6, AC-036).
            $table->index(['resource', 'status']);
            // Default sort of the dedicated table is `created_at desc` with a
            // `status` filter (data_contract endpoint columns|rows).
            $table->index(['status', 'created_at']);
            // Verified empirically (not assumed): adding a foreign key
            // constraint auto-creates a supporting index under MySQL/InnoDB
            // (the `rewards` migration relies on exactly that for
            // referent_id/reward_type_id), but SQLite's `constrained()` does
            // NOT — `PRAGMA index_list` on a constrained-only column comes
            // back empty. Since dev/test run on SQLite (CLAUDE.md §0), the
            // index is added explicitly here rather than assumed from the FK.
            $table->index('requested_by_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_change_requests');
    }
};
