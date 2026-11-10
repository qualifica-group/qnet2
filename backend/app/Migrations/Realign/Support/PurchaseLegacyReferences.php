<?php

declare(strict_types=1);

namespace App\Migrations\Realign\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Throwable;

/**
 * Reading the legacy values of the accounting documents (RDA, ODA, invoices,
 * proforma requests). Lookups are never cached across records: the simulation
 * rolls every record back.
 */
final class PurchaseLegacyReferences
{
    /** Reference appended to the notes when the import reassigned a number. */
    private const string NUMBER_REFERENCE_PATTERN = '/(?:^|\n\n)(Numero legacy .*)$/s';

    /**
     * @param  class-string<Model>  $class
     *
     * @throws RuntimeException when the reference is missing or not migrated
     */
    public function required(string $class, mixed $legacyId, string $label): int
    {
        if ($legacyId === null || $legacyId === '') {
            throw new RuntimeException("{$label} is required; the record is not updated.");
        }

        return $this->resolve($class, $legacyId)
            ?? throw new RuntimeException("{$label} (legacy id {$legacyId}) not migrated; the record is not updated.");
    }

    /**
     * A legacy reference that does not resolve keeps the current value instead of clearing it.
     *
     * @param  class-string<Model>  $class
     * @param  array<int, string>  $warnings
     */
    public function optionalOrCurrent(string $class, mixed $legacyId, mixed $current, string $label, array &$warnings): ?int
    {
        if ($legacyId === null || $legacyId === '') {
            return null;
        }

        $id = $this->resolve($class, $legacyId);

        if ($id === null) {
            $warnings[] = "{$label} (legacy id {$legacyId}) not migrated; the current value is kept.";

            return $current === null ? null : (int) $current;
        }

        return $id;
    }

    public function text(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<int, string>  $warnings
     */
    public function capped(mixed $value, int $max, string $label, array &$warnings): ?string
    {
        $text = $this->text($value);

        if ($text !== null && mb_strlen($text) > $max) {
            $warnings[] = "{$label} truncated to {$max} characters.";

            return mb_substr($text, 0, $max);
        }

        return $text;
    }

    public function timestamp(mixed $value): ?CarbonImmutable
    {
        $text = $this->text($value);

        if ($text === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse($text);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * The legacy notes, keeping the "Numero legacy" reference the import appended
     * when it had to reassign the document number.
     */
    public function notesKeepingNumberReference(?string $current, ?string $legacy): ?string
    {
        if ($current === null || preg_match(self::NUMBER_REFERENCE_PATTERN, $current, $match) !== 1) {
            return $legacy;
        }

        return $legacy === null ? $match[1] : "{$legacy}\n\n{$match[1]}";
    }

    /**
     * @param  class-string<Model>  $class
     */
    private function resolve(string $class, mixed $legacyId): ?int
    {
        $id = $class::query()->where('old_id', $legacyId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
