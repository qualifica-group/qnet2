<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Quote;
use Illuminate\Http\Request;

/**
 * For-select projection of a Quote (GET /api/quotes/for-select, ADR 0011),
 * feeding the `rewarded-referents` "Offerta" advanced filter (spec 0059
 * amendment A-01).
 *
 * `label` pairs the sequential `code` with the title: an Offerta has no single
 * descriptive column (unlike an Opportunita's derived `name`), and the code
 * alone — QUO-0007 — does not tell two offers apart at a glance. `subtitle` is
 * deliberately NOT used for the title: the picker's trigger shows the label
 * only, so a title parked in the subtitle would vanish once selected.
 *
 * @mixin Quote
 */
class QuoteForSelectResource extends ForSelectResource
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
