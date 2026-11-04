<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

/**
 * One data row of the file: its number (header = 1) and its trimmed cells keyed
 * by layout column (`ChartOfAccountsLayout::COLUMNS`).
 */
final readonly class ImportRow
{
    /**
     * @param  array<string, string>  $cells
     */
    public function __construct(public int $number, public array $cells) {}
}
