<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Registry;
use App\Models\WorkOrder;
use Illuminate\Http\Request;

/**
 * For-select projection of a WorkOrder (GET /api/work-orders/for-select, ADR
 * 0011), feeding the Task form's "Commessa" picker (spec 0101 data_contract,
 * `work_order_id`).
 *
 * `label` pairs the sequential `code` with the title, byte-identically to
 * QuoteForSelectResource and for the same two reasons: a Commessa has no
 * single descriptive column, and `COM-0007` alone does not tell two commesse
 * apart at a glance. `subtitle` is deliberately NOT used for the title — the
 * picker's trigger shows the label only, so a title parked in the subtitle
 * would vanish once selected.
 *
 * `meta.registry` (spec 0122, D-5, delta 2026-09-14 da MT-F2) is ADDITIVE: the
 * client via `quote.opportunity.registry`, `{id, name}` or null when the
 * chain is incomplete. The segnatempo form's cascading select reads it to
 * set the Cliente the moment a Commessa is chosen, mirroring the `{id, name}`
 * shape OpportunityForSelectResource already uses for its own relation refs.
 * `meta.registry_id` (spec 0154, D-11) is the SAME chain flattened to a bare
 * id: the Task form picks a commessa and needs to filter/set its own
 * `registry_id` field without unpacking the nested object.
 *
 * @mixin WorkOrder
 */
class WorkOrderForSelectResource extends ForSelectResource
{
    /** Separator between the code and the title, matching the app's other composed labels. */
    private const string LABEL_SEPARATOR = ' — ';

    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        $title = trim((string) $this->title);

        return [
            'id' => $this->id,
            'label' => $title === '' ? (string) $this->code : $this->code.self::LABEL_SEPARATOR.$title,
            'meta' => [
                'registry' => $this->registryRef($this->quote?->opportunity?->registry),
                'registry_id' => $this->quote?->opportunity?->registry_id,
            ],
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function registryRef(?Registry $registry): ?array
    {
        return $registry !== null ? ['id' => $registry->id, 'name' => $registry->name] : null;
    }
}
