<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decisione utente 2026-09-29: the supervisory copy of a transfer notification
 * now goes to whoever holds the module's `viewAll` grant, so the dedicated
 * `{module}.receiveTransferNotifications` ability (spec 0081) is no longer
 * declared by RequestManagementPolicy. `permissions:sync` only ever CREATES
 * permissions, so the existing rows are pruned here; their
 * `role_has_permissions`/`model_has_permissions` rows disappear with them
 * (cascade on the spatie pivots).
 *
 * `down()` is deliberately a no-op, same precedent as
 * `2026_08_05_110200_prune_opportunity_statuses_permissions`: nothing
 * declares these permissions any more.
 */
return new class extends Migration
{
    private const string ABILITY = 'receiveTransferNotifications';

    public function up(): void
    {
        DB::table('permissions')->where('name', 'like', '%.'.self::ABILITY)->delete();
    }

    public function down(): void
    {
        // Irreversible by design: see the docblock above.
    }
};
