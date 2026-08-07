<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `request-management` module's field change requests move their subject
 * from the Opportunity to the Quote (spec 0086, D-10 — corrected in
 * execution): `FieldChangeRequestValueResolver`/`FieldChangeRequestApprover`
 * both resolve/apply against the grid's own `TableDefinition`, whose
 * `modelClass()` becomes `Quote::class`. Every existing row was created with
 * `subject_type = opportunity`/`subject_id` = an Opportunity id — after this
 * change that id would resolve the wrong offer, or 404, on the same
 * `resource = 'request-management'`. There is no rule to re-derive "which of
 * an opportunity's quotes" a legacy request belongs to, and the database
 * carries no production data (D-8), so this migration clears those rows
 * outright. Scoped narrowly to `resource = 'request-management'` AND
 * `subject_type = 'opportunity'` (the morph alias, `AppServiceProvider`'s
 * `enforceMorphMap()`): any other resource/subject pairing in the same table
 * is untouched.
 *
 * `down()` is a DELIBERATE no-op: there is nothing to restore, the deleted
 * rows are gone — same discipline as
 * `2026_08_07_100100_reset_rewards_for_quote_source`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('field_change_requests')
            ->where('resource', 'request-management')
            ->where('subject_type', 'opportunity')
            ->delete();
    }

    public function down(): void
    {
        // No-op, deliberately: see docblock above.
    }
};
