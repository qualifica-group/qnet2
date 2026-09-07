<?php

namespace Database\Seeders;

use App\Models\OperationalSite;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Gives every TestUsersSeeder account an operational site, as the PHYSICAL
 * membership on the `employment_profile_operational_site` pivot (spec 0103,
 * replacing the former `employment_profiles.operational_site_id` column).
 *
 * Without it those accounts are invisible as operators: the Operatore select
 * filters users on that very membership when a Sede is picked (spec 0048,
 * UserService::forSelect), so an account with no employment profile never
 * appears in the list.
 *
 * Runs AFTER QualificaLegacyImportSeeder, like QualificaBusinessFunctionLinkSeeder
 * and for the same reason: it needs both sides, and the operational sites are
 * not part of the static catalogue — they come from the external qnet CRM
 * ('operational-sites' is one of that import's phase-1 sources). The accounts
 * themselves must exist earlier still, because the import runs as one of them.
 *
 * The site is picked by lowest id, NOT by alias: the aliases are whatever the
 * legacy system holds ("Napoli", "FRATTAMAGGIORE 1 (HQ)", ...) and pinning one
 * here would couple the seed to a catalogue it does not own (user decision
 * 2026-07-31).
 *
 * NEVER fatal, and never destructive: with no site imported the accounts are
 * left unassigned and the seed carries on, and an account already pointing at
 * some site keeps it — so an assignment made by hand survives the re-run, in
 * line with the rest of the Qualifica seeders.
 */
class QualificaOperatorSiteLinkSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: the site to assign. Absent when the legacy import was skipped
        // (no external system configured) — there is simply nothing to link.
        $site = OperationalSite::query()->orderBy('id')->first();

        if ($site === null) {
            $this->command?->warn('Test accounts left without an operational site: no site imported.');

            return;
        }

        // Step 2: the accounts TestUsersSeeder owns, by their natural key.
        // operationalSites is eager-loaded so the "already has a physical
        // site" check below reads off the loaded collection instead of lazy
        // loading per user.
        $users = User::query()
            ->whereIn('email', array_column(TestUsersSeeder::TEST_USERS, 'email'))
            ->with('employment.operationalSites')
            ->orderBy('id')
            ->get();

        // Step 3: fill only a free slot — an account already carrying a
        // physical site (assigned by hand, or by an earlier run) keeps it.
        // firstOrCreate on the hasOne creates the profile for an account
        // that has none yet, which is the normal case here — TestUsersSeeder
        // seeds the account alone.
        $assigned = $users
            ->reject(fn (User $user): bool => $user->employment?->primaryOperationalSiteId !== null)
            ->each(function (User $user) use ($site): void {
                $user->employment()->firstOrCreate([])
                    ->operationalSites()->syncWithoutDetaching([$site->getKey() => ['is_primary' => true]]);
            })
            ->count();

        if ($assigned === 0) {
            return;
        }

        $this->command?->info(sprintf(
            '%d test accounts assigned to operational site #%d%s.',
            $assigned,
            $site->getKey(),
            $site->alias === null ? '' : sprintf(' ("%s")', $site->alias),
        ));
    }
}
