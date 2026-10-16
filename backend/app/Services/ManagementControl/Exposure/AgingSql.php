<?php

declare(strict_types=1);

namespace App\Services\ManagementControl\Exposure;

use App\Enums\InvoicePaymentStatus;
use Illuminate\Support\Facades\DB;

/**
 * The SQL fragments of the aging and of the payment status of a schedule (spec
 * 0269, D-4/D-5). The text is constant; the only variable is the current day,
 * always a bound `?`. Days are counted as `InstallmentSql::daysOverdue()` does
 * (DATEDIFF on MySQL, julianday on SQLite). An open installment has a positive
 * residual; "days late" is today minus the due date, so it is negative while
 * the installment is still to come.
 */
final class AgingSql
{
    /** Bucket => [min, max] days late, inclusive, null for unbounded. Same keys and limits as the ledger aging. */
    public const array BUCKETS = [
        'due_0_30' => [-30, 0],
        'due_31_60' => [-60, -31],
        'due_over_60' => [null, -61],
        'overdue_1_30' => [1, 30],
        'overdue_31_60' => [31, 60],
        'overdue_61_90' => [61, 90],
        'overdue_over_90' => [91, null],
    ];

    /** Buckets from the worst to the mildest: the first with an amount names the registry's worst bucket. */
    public const array WORST_FIRST = [
        'overdue_over_90', 'overdue_61_90', 'overdue_31_60', 'overdue_1_30', 'due_over_60', 'due_31_60', 'due_0_30',
    ];

    public const string BUCKET_PREFIX = 'bucket_';

    /** Whole days late of a due date column (one binding: today). */
    public static function daysLate(string $dueColumn): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(julianday(?) - julianday({$dueColumn}) AS INTEGER)"
            : "DATEDIFF(?, {$dueColumn})";
    }

    /** Whole days from one date column to another (no binding). */
    public static function daysBetween(string $fromColumn, string $toColumn): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "CAST(julianday({$toColumn}) - julianday({$fromColumn}) AS INTEGER)"
            : "DATEDIFF({$toColumn}, {$fromColumn})";
    }

    /**
     * One SUM per bucket over the open residuals, aliased `bucket_<key>`.
     *
     * @return array{0: string, 1: array<int, string>} select text and its bindings
     */
    public static function buckets(string $dueColumn, string $residualColumn, string $today): array
    {
        $days = self::daysLate($dueColumn);
        $columns = [];
        $bindings = [];
        foreach (self::BUCKETS as $key => [$min, $max]) {
            $range = array_filter([
                $min === null ? null : "{$days} >= {$min}",
                $max === null ? null : "{$days} <= {$max}",
            ]);
            $columns[] = "SUM(CASE WHEN {$residualColumn} > 0 AND ".implode(' AND ', $range)." THEN {$residualColumn} ELSE 0 END) AS ".self::BUCKET_PREFIX.$key;
            array_push($bindings, ...array_fill(0, count($range), $today));
        }

        return [implode(', ', $columns), $bindings];
    }

    /**
     * Open and overdue residual, aliased `open_total` / `overdue_total`.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function totals(string $dueColumn, string $residualColumn, string $today): array
    {
        return [
            "SUM(CASE WHEN {$residualColumn} > 0 THEN {$residualColumn} ELSE 0 END) AS open_total, "
            ."SUM(CASE WHEN {$residualColumn} > 0 AND ".self::daysLate($dueColumn)." > 0 THEN {$residualColumn} ELSE 0 END) AS overdue_total",
            [$today],
        ];
    }

    /**
     * The payment status of a document from its oldest open due date (D-5). A
     * document with no installments at all is not due, as the resolver does.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    public static function status(string $presentColumn, string $oldestDueColumn, string $today): array
    {
        $days = self::daysLate($oldestDueColumn);
        $notDue = InvoicePaymentStatus::NotDue->value;

        return [
            "CASE WHEN {$presentColumn} IS NULL THEN '{$notDue}'"
            ." WHEN {$oldestDueColumn} IS NULL THEN '".InvoicePaymentStatus::Paid->value."'"
            ." WHEN {$days} <= 0 THEN '{$notDue}'"
            ." WHEN {$days} > ".InvoicePaymentStatus::SERIOUSLY_OVERDUE_DAYS." THEN '".InvoicePaymentStatus::SeriouslyOverdue->value."'"
            ." ELSE '".InvoicePaymentStatus::Overdue->value."' END",
            [$today, $today],
        ];
    }
}
