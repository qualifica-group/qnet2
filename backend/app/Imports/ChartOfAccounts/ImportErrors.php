<?php

declare(strict_types=1);

namespace App\Imports\ChartOfAccounts;

use App\Exports\ChartOfAccounts\ChartOfAccountsLayout;

/**
 * Collects the row errors of an import. `row` is the file row number and
 * `column` the layout column key, null for an error of the whole row.
 */
final class ImportErrors
{
    /** @var list<array{row: int, column: ?string, message: string}> */
    private array $errors = [];

    /**
     * @param  array<string, scalar>  $replace
     */
    public function add(int $row, ?string $column, string $messageKey, array $replace = []): void
    {
        $this->errors[] = ['row' => $row, 'column' => $column, 'message' => __("chart_accounts.io.errors.{$messageKey}", $replace)];
    }

    public function count(): int
    {
        return count($this->errors);
    }

    /**
     * Response shape: ordered by row, columns named by the user's header.
     *
     * @return list<array{row: int, column: ?string, message: string}>
     */
    public function toResponse(): array
    {
        $errors = $this->errors;
        usort($errors, static fn (array $a, array $b): int => $a['row'] <=> $b['row']);

        return array_map(static fn (array $error): array => [
            ...$error,
            'column' => $error['column'] === null ? null : ChartOfAccountsLayout::header($error['column']),
        ], $errors);
    }
}
