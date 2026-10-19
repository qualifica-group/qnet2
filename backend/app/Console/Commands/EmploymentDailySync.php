<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmploymentProfile;
use App\Models\User;
use App\Services\EmploymentContracts\EmploymentContractService;
use Illuminate\Console\Command;

/**
 * Daily HR realignment (spec 0272 D-8): deactivates the active users whose
 * termination date has arrived and mirrors, on every profile with a contract
 * history, the condition now in force. Future terminations and future
 * conditions are left alone. Idempotent; scheduled in routes/console.php.
 */
class EmploymentDailySync extends Command
{
    protected $signature = 'employment:daily-sync';

    protected $description = 'Deactivate terminated users and realign the current contract condition on employment profiles';

    private const int CHUNK = 200;

    public function handle(EmploymentContractService $contracts): int
    {
        // Step 1: terminations that have arrived
        $deactivated = $this->deactivateTerminated();

        // Step 2: the condition in force on every profile with a history
        $realigned = $this->realignCurrentConditions($contracts);

        $this->info("Deactivated {$deactivated} user(s); realigned {$realigned} profile(s).");

        return self::SUCCESS;
    }

    private function deactivateTerminated(): int
    {
        $count = 0;

        User::query()
            ->where('is_active', true)
            ->whereHas('employment', fn ($query) => $query->whereDate('terminated_at', '<=', today()->toDateString()))
            ->chunkById(self::CHUNK, function ($users) use (&$count): void {
                foreach ($users as $user) {
                    // Model update so the activity log records it.
                    $user->update(['is_active' => false]);
                    $count++;
                }
            });

        return $count;
    }

    private function realignCurrentConditions(EmploymentContractService $contracts): int
    {
        $count = 0;

        EmploymentProfile::query()
            ->whereHas('contracts')
            ->chunkById(self::CHUNK, function ($profiles) use ($contracts, &$count): void {
                foreach ($profiles as $profile) {
                    $contracts->syncCurrent($profile);
                    $count++;
                }
            });

        return $count;
    }
}
