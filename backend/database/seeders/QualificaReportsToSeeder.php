<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\QualificaCatalog\ReportsToRoster;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Who each operator reports to (ReportsToRoster, user directive 2026-09-25),
 * written on the `employment_profile_manager` pivot of spec 0166.
 *
 * Runs AFTER QualificaOperatorSeeder and QualificaStaffSeeder: both the
 * operators and their managers must already have an account.
 *
 * CONVERGENT, like the operator roster it complements: every listed account's
 * managers are re-synced from the roster on every run. An account the roster
 * does not list is never touched.
 *
 * NEVER fatal: a missing account or manager is skipped with a warning; so is
 * a profile flagged `is_manager`, which by spec 0166 D-5 reports to no one.
 */
class QualificaReportsToSeeder extends Seeder
{
    public function run(): void
    {
        // Step 1: every account the roster names, read once.
        $usersByEmail = $this->usersByEmail();

        // Step 2: one pivot sync per listed operator.
        $synced = 0;

        foreach (ReportsToRoster::MANAGERS as $email => $managerEmails) {
            $user = $usersByEmail->get($email);

            if ($user === null) {
                $this->command?->warn(sprintf('%s: no account, reports-to skipped.', $email));

                continue;
            }

            if ($this->syncManagers($user, $this->managerIds($usersByEmail, $email, $managerEmails))) {
                $synced++;
            }
        }

        $this->command?->info(sprintf('%d reports-to sets synced.', $synced));
    }

    /**
     * @return Collection<string, User>
     */
    private function usersByEmail(): Collection
    {
        $emails = collect(ReportsToRoster::MANAGERS)->keys()
            ->merge(collect(ReportsToRoster::MANAGERS)->flatten())
            ->unique();

        return User::query()->whereIn('email', $emails)->with('employment')->get()->keyBy('email');
    }

    /**
     * @param  Collection<string, User>  $usersByEmail
     * @param  list<string>  $managerEmails
     * @return list<int>
     */
    private function managerIds(Collection $usersByEmail, string $email, array $managerEmails): array
    {
        $ids = [];

        foreach ($managerEmails as $managerEmail) {
            $manager = $usersByEmail->get($managerEmail);

            if ($manager === null) {
                $this->command?->warn(sprintf('%s: manager %s has no account, skipped.', $email, $managerEmail));

                continue;
            }

            $ids[] = $manager->id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $managerIds
     */
    private function syncManagers(User $user, array $managerIds): bool
    {
        // A staff account has no profile yet: the pivot hangs off one.
        $employment = $user->employment ?? $user->employment()->create();

        if ($employment->is_manager && $managerIds !== []) {
            $this->command?->warn(sprintf('%s: flagged as manager, reports to no one (spec 0166 D-5).', $user->email));

            return false;
        }

        $employment->reportsTo()->sync($managerIds);

        return true;
    }
}
