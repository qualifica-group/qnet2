<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Contract;
use App\Support\ForSelectSubtitle;
use Illuminate\Http\Request;

/**
 * For-select projection of a Contract (ADR 0011, spec 0347): `label` is its quote code and title (a contract has no code of its own), `subtitle` the client and the acceptance date.
 *
 * @mixin Contract
 */
class ContractForSelectResource extends ForSelectResource
{
    /** Separator between the quote code and title, matching QuoteForSelectResource. */
    private const string LABEL_SEPARATOR = ' — ';

    private function label(): string
    {
        if ($this->quote === null) {
            return '#'.$this->id;
        }

        $title = trim((string) $this->quote->title);

        return $title === '' ? (string) $this->quote->code : $this->quote->code.self::LABEL_SEPARATOR.$title;
    }

    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label(),
            'subtitle' => ForSelectSubtitle::compose($this->quote?->opportunity?->registry?->name, $this->accepted_at),
        ];
    }
}
