<?php

declare(strict_types=1);

namespace App\Migrations\Support;

/**
 * The general notes of a legacy opportunity (spec 0189): its own notes, the
 * reserved notes, then the "Note" of each Info preliminari card, every block
 * after the first under its own label. A re-import appends to an already
 * imported opportunity only the card notes its general notes still lack.
 */
final class LegacyOpportunityNotes
{
    private const string RESERVED_NOTES_LABEL = 'Note riservate:';

    private const string BLOCK_SEPARATOR = "\n\n";

    /**
     * @param  array<string, mixed>  $record
     */
    public function compose(array $record): ?string
    {
        $reserved = $this->blankToNull($record['reserved_notes'] ?? null);

        return $this->join([
            $this->blankToNull($record['notes'] ?? null),
            $reserved === null ? null : self::RESERVED_NOTES_LABEL."\n".$reserved,
            ...$this->preliminaryInfoBlocks($record),
        ]);
    }

    /**
     * The current general notes followed by the card notes they lack; null
     * when nothing is missing.
     *
     * @param  array<string, mixed>  $record
     */
    public function withMissingPreliminaryInfo(?string $current, array $record): ?string
    {
        $missing = array_filter(
            $this->preliminaryInfoBlocks($record),
            static fn (string $block): bool => ! str_contains((string) $current, $block),
        );

        return $missing === [] ? null : $this->join([$this->blankToNull($current), ...$missing]);
    }

    /**
     * @param  array<string, mixed>  $record
     * @return list<string>
     */
    private function preliminaryInfoBlocks(array $record): array
    {
        $blocks = [];

        foreach ((array) ($record['preliminary_info_notes'] ?? []) as $entry) {
            $card = $this->blankToNull($entry['card'] ?? null);
            $note = $this->blankToNull($entry['note'] ?? null);

            if ($card !== null && $note !== null) {
                $blocks[] = "{$card}:\n{$note}";
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, string|null>  $blocks
     */
    private function join(array $blocks): ?string
    {
        $blocks = array_filter($blocks, static fn (?string $block): bool => $block !== null);

        return $blocks === [] ? null : implode(self::BLOCK_SEPARATOR, $blocks);
    }

    private function blankToNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
