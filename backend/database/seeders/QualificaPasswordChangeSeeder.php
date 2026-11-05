<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production only (spec 0356): every active, non-service user who never chose
 * their own password (`password_changed_at` null) must pick one at the next
 * access. No email, no token revocation: the first-access gate of spec 0355
 * shows at their next request. Safe to re-run; also the last step of
 * QualificaProductionDataSeeder, or alone:
 * `php artisan db:seed --class=QualificaPasswordChangeSeeder`.
 */
class QualificaPasswordChangeSeeder extends Seeder
{
    public function run(): void
    {
        if (! config('seeding.require_password_change')) {
            $this->command?->info('Obbligo di cambio password disattivato (solo produzione): nessun utente modificato.');

            return;
        }

        // A query update: `must_set_password` is guarded and no model event is
        // needed for a flag nobody else listens to.
        $flagged = User::query()
            ->where('is_active', true)
            ->where('is_service_account', false)
            ->whereNull('password_changed_at')
            ->where('must_set_password', false)
            ->update(['must_set_password' => true]);

        $this->command?->info(sprintf('%d utenti attivi dovranno cambiare password al prossimo accesso.', $flagged));
    }
}
