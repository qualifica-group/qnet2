<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChartOfAccounts\RevenueLineClassifier;
use Illuminate\Console\Command;

/**
 * Classifies every invoice line without a revenue sub-account that the
 * proposal rule (spec 0350, D-6) can classify. Idempotent: a second run finds
 * nothing left to do, and a line that already has a value is never touched.
 */
class ClassifyRevenueLines extends Command
{
    protected $signature = 'chart:classify-revenue';

    protected $description = 'Classify the unclassified invoice lines with the proposed revenue sub-account';

    public function handle(RevenueLineClassifier $classifier): int
    {
        $classified = $classifier->classifyAll();

        $this->info("{$classified} line(s) classified.");

        return self::SUCCESS;
    }
}
