<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotes;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Body of the "transfer to another anagrafica" action (spec 0404, part B), on
 * the Offerte route and on the Gestione Richieste/Iscritti ones. The
 * "Informazioni aggiuntive" are validated per code by
 * QuoteAttributeValueWriter against the offer's set once moved, like the
 * offer update; the optional `title` follows the offer update's title rules
 * (spec 0404 B-D8). Authorization lives in the controller (Policy/permissions).
 */
class TransferQuoteRegistryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'registry_id' => ['required', 'integer', 'exists:registries,id'],
            // B-D8: absent = untouched, null/blank = back to the automatic title.
            'title' => ['sometimes', 'nullable', 'string', 'max:191'],
            'attribute_values' => ['sometimes', 'array'],
        ];
    }

    public function registryId(): int
    {
        return $this->safe()->integer('registry_id');
    }

    public function titleSubmitted(): bool
    {
        return $this->safe()->has('title');
    }

    public function title(): ?string
    {
        $title = $this->safe()->input('title');

        return $title === null ? null : (string) $title;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function attributeValues(): ?array
    {
        return $this->safe()->has('attribute_values') ? (array) $this->safe()->input('attribute_values') : null;
    }
}
