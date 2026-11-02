<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Spec 0328: addresses and contacts are authorized through the record that
 * owns their personal-data card, so AddressPolicy and ContactPolicy no longer
 * declare `addresses.*` / `contacts.*`. `permissions:sync` only ever CREATES
 * permissions, so the existing rows are pruned here; their
 * `role_has_permissions`/`model_has_permissions` rows disappear with them
 * (cascade on the spatie pivots).
 *
 * `down()` is deliberately a no-op, same precedent as
 * `2026_09_29_120000_prune_receive_transfer_notifications_permissions`:
 * nothing declares these permissions any more.
 */
return new class extends Migration
{
    private const array RESOURCES = ['addresses', 'contacts'];

    public function up(): void
    {
        foreach (self::RESOURCES as $resource) {
            DB::table('permissions')->where('name', 'like', $resource.'.%')->delete();
        }
    }

    public function down(): void
    {
        // Irreversible by design: see the docblock above.
    }
};
