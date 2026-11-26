<?php

declare(strict_types=1);

namespace App\Imports\PayrollCosts;

/**
 * Collects the row errors of a cost import. `row` is the file line and
 * `column` the header as written in the file, null for an error of the row.
 */
final class PayrollCostImportErrors
{
    /** @var list<array{row: int, column: ?string, message: string}> */
    private array $errors = [];

    /**
     * @param  array<string, string>  $headers  column key => header in the file
     */
    public function __construct(private readonly array $headers) {}

    /**
     * @param  array<string, scalar>  $replace
     */
    public function add(int $row, ?string $columnKey, string $messageKey, array $replace = []): void
    {
        $this->errors[] = [
            'row' => $row,
            'column' => $columnKey === null ? null : ($this->headers[$columnKey] ?? null),
            'message' => __("payroll_costs.import.errors.{$messageKey}", $replace),
        ];
    }

    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * @return list<array{row: int, column: ?string, message: string}>
     */
    public function toResponse(): array
    {
        $errors = $this->errors;
        usort($errors, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return $errors;
    }
}
