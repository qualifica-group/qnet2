<?php

declare(strict_types=1);

namespace App\Http\Requests\WorkOrderEmails\Concerns;

use Illuminate\Contracts\Validation\Validator;

/**
 * Shared `to`/`cc`/`bcc` handling for StoreOutboundEmailRequest and
 * UpdateOutboundEmailRequest (spec 0175, D-5): both accept the SAME optional
 * recipient fields (a draft may be saved with none at all).
 *
 * Normalization (trim + dedup PER FIELD, no domain-casing change — D-5:
 * "lascia com'è") happens BEFORE validation, in prepareForValidation(), so
 * `email:rfc` validates the trimmed value and a duplicate address submitted
 * twice in the SAME field never inflates the recipient-count check below.
 */
trait ValidatesRecipients
{
    /**
     * @return array<string, array<int, mixed>>
     */
    protected function recipientRules(): array
    {
        return [
            'to' => ['sometimes', 'array'],
            'to.*' => ['email:rfc'],
            'cc' => ['sometimes', 'array'],
            'cc.*' => ['email:rfc'],
            'bcc' => ['sometimes', 'array'],
            'bcc.*' => ['email:rfc'],
        ];
    }

    protected function normalizeRecipientsForValidation(): void
    {
        $merge = [];

        foreach (['to', 'cc', 'bcc'] as $field) {
            $value = $this->input($field);

            if (! is_array($value)) {
                continue;
            }

            $merge[$field] = array_values(array_unique(array_map(
                static fn ($address) => is_string($address) ? trim($address) : $address,
                $value,
            )));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * D-5: the combined to+cc+bcc count over config('outbound_emails.max_recipients')
     * errors on the `to` key specifically (spec 0175 decision, not a generic
     * "too many" key spread across all three fields).
     */
    protected function assertRecipientLimit(Validator $validator): void
    {
        $total = count((array) $this->input('to', []))
            + count((array) $this->input('cc', []))
            + count((array) $this->input('bcc', []));

        $max = (int) config('outbound_emails.max_recipients');

        if ($total > $max) {
            $validator->errors()->add('to', __('outbound_emails.too_many_recipients', ['max' => $max]));
        }
    }
}
