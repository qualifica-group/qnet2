<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
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
        ];
    }
}
