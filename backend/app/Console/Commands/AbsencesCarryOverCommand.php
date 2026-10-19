<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Absences\Balances\AbsenceCarryOverService;
use Illuminate\Console\Command;

/**
 * Yearly absence carry-over (spec 0278 D-11): moves the residual of each
 * allowance of the previous year into the given year (default: the current
 * one). Idempotent; scheduled for January 1st in routes/console.php.
 */
class AbsencesCarryOverCommand extends Command
{
    protected $signature = 'absences:carry-over {year? : Target year (default: current year)}';

    protected $description = 'Carry the residual absence balance of the previous year over to the given year';

    public function handle(AbsenceCarryOverService $service): int
    {
        // Step 1: resolve the target year
        $year = $this->argument('year');

        if ($year !== null && (! ctype_digit((string) $year) || (int) $year < 2001 || (int) $year > 2100)) {
            $this->error('The year must be an integer between 2001 and 2100.');

            return self::FAILURE;
        }

        $year = $year === null ? (int) today()->year : (int) $year;

        // Step 2: carry over
        $count = $service->carryOver($year);

        $this->info("Carried over {$count} allowance(s) into {$year}.");

        return self::SUCCESS;
    }
}
