<?php

declare(strict_types=1);

namespace App\Imports\Payslips;

/**
 * Collects the row errors and warnings of a net file import (spec 0431). `row`
 * is the file line and `column` the header as written in the file, null for an
 * issue of the whole row.
 */
final class PayslipIssues
{
    /** @var list<array{row: int, column: ?string, message: string}> */
    private array $errors = [];

    /** @var list<array{row: int, column: ?string, message: string}> */
    private array $warnings = [];

    /**
     * @param  array<string, string>  $headers  column key => header in the file
     */
    public function __construct(private readonly array $headers) {}

    /**
     * @param  array<string, scalar>  $replace
     */
    public function error(int $row, ?string $columnKey, string $messageKey, array $replace = []): void
    {
        $this->errors[] = $this->issue($row, $columnKey, "payslips.import.errors.{$messageKey}", $replace);
    }

    /**
     * @param  array<string, scalar>  $replace
     */
    public function warning(int $row, ?string $columnKey, string $messageKey, array $replace = []): void
    {
        $this->warnings[] = $this->issue($row, $columnKey, "payslips.import.warnings.{$messageKey}", $replace);
    }

    public function errorCount(): int
    {
        return count($this->errors);
    }

    /**
     * @return list<array{row: int, column: ?string, message: string}>
     */
    public function errors(): array
    {
        return self::sorted($this->errors);
    }

    /**
     * @return list<array{row: int, column: ?string, message: string}>
     */
    public function warnings(): array
    {
        return self::sorted($this->warnings);
    }

    /**
     * @param  array<string, scalar>  $replace
     * @return array{row: int, column: ?string, message: string}
     */
    private function issue(int $row, ?string $columnKey, string $translationKey, array $replace): array
    {
        return [
            'row' => $row,
            'column' => $columnKey === null ? null : ($this->headers[$columnKey] ?? null),
            'message' => __($translationKey, $replace),
        ];
    }

    /**
     * @param  list<array{row: int, column: ?string, message: string}>  $issues
     * @return list<array{row: int, column: ?string, message: string}>
     */
    private static function sorted(array $issues): array
    {
        usort($issues, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $issues;
    }
}
