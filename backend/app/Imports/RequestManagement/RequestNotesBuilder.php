<?php

namespace App\Imports\RequestManagement;

/**
 * Composes the Opportunity's general notes for an imported row (spec 0348,
 * AC-008/010/011): the row's own notes cell, then every value the import could
 * not store ("Prodotto: ???"), then every file column left unmapped
 * ("Intestazione: valore"). Empty cells add nothing.
 */
final class RequestNotesBuilder
{
    /**
     * @param  array<string, mixed>  $mapped  value set after recognizers (`notes`, `discarded_values`)
     * @param  array<string, mixed>  $extraValues  unmapped file column key => cell
     */
    public function build(array $mapped, array $extraValues): ?string
    {
        $lines = [];
        $own = trim((string) ($mapped['notes'] ?? ''));

        if ($own !== '') {
            $lines[] = $own;
        }

        foreach ((array) ($mapped['discarded_values'] ?? []) as $discarded) {
            $lines[] = "{$discarded['label']}: {$discarded['value']}";
        }

        foreach ($extraValues as $header => $cell) {
            $text = trim((string) $cell);

            if ($text !== '') {
                $lines[] = "{$header}: {$text}";
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }
}
