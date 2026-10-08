<?php

declare(strict_types=1);

namespace App\Tables\Shared;

use App\Models\QuoteWorkflowStatus;

/**
 * The `select` editor catalogue of a `quote_workflow_status` column, shared by
 * the Gestione Richieste and Offerte grids (spec 0206, D-6): every workflow's
 * statuses (GET /columns is domain-wide, not per-row), each carrying `color`
 * for the option dot and `requires_note` so the editor opens the transition
 * note dialog. The per-row narrowing rides on the row's own
 * `quote_workflow_status_options`; the authoritative membership check stays
 * in QuoteWorkflowStatusWriter.
 */
final class QuoteWorkflowStatusOptions
{
    /**
     * @return array<int, array{value: int, label: string, color: string|null, requires_note: bool}>
     */
    public static function catalog(): array
    {
        return QuoteWorkflowStatus::query()
            ->orderBy('sort_order')
            ->get(['id', 'name', 'color', 'requires_note'])
            ->map(static fn (QuoteWorkflowStatus $status): array => [
                'value' => $status->id,
                'label' => $status->name,
                'color' => $status->color,
                'requires_note' => (bool) $status->requires_note,
            ])
            ->all();
    }
}
