<?php

declare(strict_types=1);

namespace App\Migrations\Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Helpers shared by the writers of the legacy accounting documents (specs
 * 0296, 0298, 0301): the required line description within the column cap,
 * an optional `old_id` reference that degrades to a warning, and the legacy
 * schedule whose last installment absorbs the difference from the qnet-2
 * total.
 */
trait BuildsLegacyDocumentLines
{
    private const int LINE_DESCRIPTION_MAX = 500;

    /**
     * @param  array<int, string>  $warnings
     *
     * @throws RuntimeException when the description is blank
     */
    private function lineDescription(mixed $legacyDescription, string $label, array &$warnings): string
    {
        $description = trim((string) ($legacyDescription ?? ''));

        if ($description === '') {
            throw new RuntimeException("{$label}: description is required.");
        }

        if (mb_strlen($description) > self::LINE_DESCRIPTION_MAX) {
            $warnings[] = "{$label}: description truncated to ".self::LINE_DESCRIPTION_MAX.' characters.';

            return mb_substr($description, 0, self::LINE_DESCRIPTION_MAX);
        }

        return $description;
    }

    /**
     * @param  class-string<Model>  $targetClass
     * @param  array<int, string>  $warnings
     */
    private function optionalOldId(string $targetClass, mixed $legacyId, string $label, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $id = $targetClass::query()->where('old_id', $legacyId)->value('id');

        if ($id === null) {
            $warnings[] = "{$label} (legacy id {$legacyId}) not migrated, left empty.";
        }

        return $id;
    }

    /**
     * @param  array<int, array<string, mixed>>  $installments  each with a 2-decimal `amount`
     * @param  array<int, string>  $warnings
     * @return array<int, array<string, mixed>>
     */
    private function absorbDifference(array $installments, string $total, string $document, array &$warnings): array
    {
        $sum = array_reduce($installments, fn (string $carry, array $installment): string => bcadd($carry, (string) $installment['amount'], 2), '0.00');
        $difference = bcsub($total, $sum, 2);

        if (bccomp($difference, '0', 2) !== 0) {
            $last = array_key_last($installments);
            $installments[$last]['amount'] = bcadd((string) $installments[$last]['amount'], $difference, 2);
            $warnings[] = "The legacy installments sum to {$sum}, the {$document} total is {$total}: the last installment absorbs the difference.";
        }

        return $installments;
    }
}
