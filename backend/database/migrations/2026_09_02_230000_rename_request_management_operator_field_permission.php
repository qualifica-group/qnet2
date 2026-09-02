<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0097 (D-3c): the `request-management` field-permission key
 * `operator_id` is replaced by `manager_slots` — the panel now edits the
 * Offerta's whole team instead of its lone GA2 "Operatore"
 * (RequestManagementAuthorization::fields()). `role_field_permissions` is
 * keyed on (`role_id`,`resource`,`field`), so without this pass every row an
 * administrator already configured on the old key would become a silent
 * orphan: the matrix would read as "unrestricted" for a field that IS
 * restricted (AC-006). The rows are therefore RENAMED in place, keeping
 * their visible/editable/required flags and their id.
 *
 * The UNIQUE index makes the rename fail if a role somehow already carries a
 * `manager_slots` row for this resource (nothing writes one today — the
 * matrix endpoint validates every field against the AuthorizationRegistry,
 * which never declared both keys at once — but a hand-edited or
 * partially-migrated database could). Such a row is dropped FIRST: the
 * administrator's real configuration is the one on the key the UI has been
 * writing all along, and it is what AC-006 promises to preserve.
 *
 * `down()` is the exact mirror, and the RENAME half round-trips losslessly.
 * The conflict half does not, by construction: a dropped row cannot be put
 * back. That asymmetry is the price of honouring the UNIQUE index without
 * aborting the migration, and it only ever bites a database that already
 * held both keys at once — a state nothing in this codebase can produce.
 */
return new class extends Migration
{
    private const string RESOURCE = 'request-management';

    private const string OLD_FIELD = 'operator_id';

    private const string NEW_FIELD = 'manager_slots';

    public function up(): void
    {
        $this->renameField(self::OLD_FIELD, self::NEW_FIELD);
    }

    public function down(): void
    {
        $this->renameField(self::NEW_FIELD, self::OLD_FIELD);
    }

    /**
     * Two statements rather than one: MySQL forbids a DELETE whose subquery
     * selects from the very table being deleted from, so the conflicting
     * role ids are READ first and handed over as plain values — identical
     * behaviour on MySQL (prod) and SQLite (tests).
     */
    private function renameField(string $from, string $to): void
    {
        $conflictingRoleIds = DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->pluck('role_id')
            ->all();

        if ($conflictingRoleIds !== []) {
            DB::table('role_field_permissions')
                ->where('resource', self::RESOURCE)
                ->where('field', $to)
                ->whereIn('role_id', $conflictingRoleIds)
                ->delete();
        }

        DB::table('role_field_permissions')
            ->where('resource', self::RESOURCE)
            ->where('field', $from)
            ->update(['field' => $to]);
    }
};
