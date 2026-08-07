<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `rewards.source` moves from the Opportunity to the Offerta (spec 0086,
 * D-4/D-8): every existing row was assigned against `source_type = opportunity`
 * and a beneficiary read from `opportunity.reporter_id` — neither survives the
 * new contract (`source_type = quote`, beneficiary `quote.reporter_id`), and
 * there is no rule to re-derive "which of an opportunity's quotes" a legacy
 * row belongs to. The database carries no production data (D-8), so this
 * migration clears the table outright; `DemoDataSeeder` (DemoRewardSeeder)
 * repopulates it against quotes on the next run.
 *
 * `down()` is a DELIBERATE no-op: there is nothing to restore, the deleted
 * rows are gone. Documented rather than faked reversible — see
 * `engineering.md §1.5`/`backend.md §3`, migrations must not pretend a
 * reversibility they do not have.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('rewards')->delete();
    }

    public function down(): void
    {
        // No-op, deliberately: see docblock above.
    }
};
