<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Enums\CommissionType;
use App\Models\QuoteLineCommission;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Shared validation for a quote's `offer_lines`/`cost_lines` payload (spec
 * 0065, D-11): both tabs share the SAME row shape and the SAME rules —
 * `line_type` is never part of the payload, it is stamped server-side by
 * which array a row came from. Used verbatim by StoreQuoteRequest and
 * UpdateQuoteRequest (every field is `sometimes` on both: the array itself
 * is optional on create too, AC-030 fixtures aside).
 *
 * `net_amount`/`vat_amount`/`total_amount` are `prohibited` (AC-033): they
 * are server-computed (QuoteTotalsCalculator), never client input.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesQuoteLines
{
    protected function enforceCommissionFieldPermissions(Validator $validator, ?Model $quote): void
    {
        if (! $this->has('offer_lines')) {
            return;
        }

        /** @var User $actor */
        $actor = $this->user();
        $baseAbility = $quote === null ? 'create' : 'update';

        if (! $actor->can("quotes.{$baseAbility}")) {
            return;
        }

        $permissions = app(AuthorizationRegistry::class)
            ->resolve('quotes')
            ->fieldPermissions($actor, $quote);
        $fieldMap = [
            'recipient_type' => 'commission_recipient',
            'recipient_id' => 'commission_recipient',
            'commission_type' => 'commission_type',
            'value' => 'commission_value',
            'internal_note' => 'commission_internal_note',
        ];
        $existing = $quote === null
            ? collect()
            : QuoteLineCommission::query()
                ->whereHas('quoteLine', fn ($query) => $query->where('quote_id', $quote->getKey()))
                ->get()
                ->keyBy(fn (QuoteLineCommission $commission): string => $this->commissionKey(
                    $commission->quote_line_id,
                    $commission->recipient_role->value,
                ));

        foreach ((array) $this->input('offer_lines', []) as $lineIndex => $line) {
            if (! is_array($line) || ! array_key_exists('commissions', $line)) {
                continue;
            }

            if (! ($permissions['commissions']->editable ?? false)) {
                if ($this->commissionCollectionChanged($line, $existing)) {
                    $validator->errors()->add("offer_lines.{$lineIndex}.commissions", 'field not editable');
                }

                continue;
            }

            foreach ((array) $line['commissions'] as $commissionIndex => $commission) {
                if (! is_array($commission)
                    || ($commission['origin'] ?? null) !== CommissionOrigin::ManualOverride->value) {
                    // Configuration-derived fields are ignored and resolved
                    // again by the server, so they cannot mutate protected
                    // values even when submitted by a stale client.
                    continue;
                }

                $persisted = isset($line['id'], $commission['recipient_role'])
                    ? $existing->get($this->commissionKey((int) $line['id'], (string) $commission['recipient_role']))
                    : null;

                foreach ($fieldMap as $inputField => $permissionField) {
                    if (array_key_exists($inputField, $commission)
                        && ! ($permissions[$permissionField]->editable ?? false)
                        && $this->commissionFieldChanged($persisted, $inputField, $commission[$inputField])) {
                        $validator->errors()->add(
                            "offer_lines.{$lineIndex}.commissions.{$commissionIndex}.{$inputField}",
                            'field not editable',
                        );
                    }
                }
            }
        }
    }

    private function commissionCollectionChanged(array $line, Collection $existing): bool
    {
        $lineId = isset($line['id']) ? (int) $line['id'] : null;
        $current = $lineId === null
            ? []
            : $existing
                ->filter(fn (QuoteLineCommission $commission): bool => $commission->quote_line_id === $lineId)
                ->map(fn (QuoteLineCommission $commission): array => [
                    'recipient_role' => $commission->recipient_role->value,
                    'recipient_type' => $commission->recipient_type,
                    'recipient_id' => $commission->recipient_id,
                    'commission_type' => $commission->commission_type->value,
                    'value' => number_format((float) $commission->value, 4, '.', ''),
                    'internal_note' => $commission->internal_note,
                    'origin' => $commission->origin->value,
                    'commission_configuration_id' => $commission->commission_configuration_id,
                ])
                ->sortBy('recipient_role')
                ->values()
                ->all();
        $submitted = collect((array) ($line['commissions'] ?? []))
            ->map(static fn (mixed $commission): array => [
                'recipient_role' => (string) data_get($commission, 'recipient_role'),
                'recipient_type' => data_get($commission, 'recipient_type'),
                'recipient_id' => data_get($commission, 'recipient_id') === null
                    ? null
                    : (int) data_get($commission, 'recipient_id'),
                'commission_type' => (string) data_get($commission, 'commission_type'),
                'value' => number_format((float) data_get($commission, 'value'), 4, '.', ''),
                'internal_note' => data_get($commission, 'internal_note') === ''
                    ? null
                    : data_get($commission, 'internal_note'),
                'origin' => (string) data_get($commission, 'origin'),
                'commission_configuration_id' => data_get($commission, 'commission_configuration_id') === null
                    ? null
                    : (int) data_get($commission, 'commission_configuration_id'),
            ])
            ->sortBy('recipient_role')
            ->values()
            ->all();

        return $current !== $submitted;
    }

    private function commissionKey(int $lineId, string $role): string
    {
        return "{$lineId}:{$role}";
    }

    private function commissionFieldChanged(
        ?QuoteLineCommission $persisted,
        string $field,
        mixed $submitted,
    ): bool {
        if ($persisted === null) {
            return true;
        }

        $current = match ($field) {
            'commission_type' => $persisted->commission_type->value,
            'value' => number_format((float) $persisted->value, 4, '.', ''),
            'recipient_id' => $persisted->recipient_id,
            'recipient_type' => $persisted->recipient_type,
            'internal_note' => $persisted->internal_note,
            default => null,
        };
        $normalized = match ($field) {
            'value' => number_format((float) $submitted, 4, '.', ''),
            'recipient_id' => $submitted === null ? null : (int) $submitted,
            'internal_note' => $submitted === '' ? null : $submitted,
            default => $submitted,
        };

        return $current !== $normalized;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function quoteLinesRules(): array
    {
        return array_merge(
            $this->quoteLineFieldRules('offer_lines'),
            $this->quoteLineFieldRules('cost_lines'),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function quoteLineFieldRules(string $field): array
    {
        return [
            $field => ['sometimes', 'array', 'max:200'],
            "{$field}.*.product_id" => ['required', 'integer', Rule::exists('products', 'id')],
            "{$field}.*.id" => ['nullable', 'integer', Rule::exists('quote_lines', 'id')],
            // gt:0 (AC-034: 0 and negative rejected); decimal:0,2 caps the
            // input to <= 2 decimal places (AC-032), the SAME rounding scale
            // QuoteTotalsCalculator freezes on the row.
            "{$field}.*.quantity" => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999.99'],
            // unit_price 0 IS accepted (AC-034); negative is not.
            "{$field}.*.unit_price" => ['required', 'numeric', 'min:0', 'decimal:0,2', 'max:99999999.99'],
            "{$field}.*.vat_rate_id" => ['nullable', 'integer', Rule::exists('vat_rates', 'id')],
            "{$field}.*.sort_order" => ['nullable', 'integer', 'min:0'],
            "{$field}.*.net_amount" => ['prohibited'],
            "{$field}.*.vat_amount" => ['prohibited'],
            "{$field}.*.total_amount" => ['prohibited'],
            "{$field}.*.commissions" => $field === 'offer_lines'
                ? ['sometimes', 'array', 'max:4']
                : ['prohibited'],
            "{$field}.*.commissions.*.id" => ['nullable', 'integer', Rule::exists('quote_line_commissions', 'id')],
            "{$field}.*.commissions.*.recipient_role" => ['required', Rule::enum(CommissionRecipientRole::class), 'distinct'],
            "{$field}.*.commissions.*.recipient_type" => ['required', Rule::in(['referent', 'user', 'registry'])],
            "{$field}.*.commissions.*.recipient_id" => ['required', 'integer'],
            "{$field}.*.commissions.*.commission_type" => ['required', Rule::enum(CommissionType::class)],
            "{$field}.*.commissions.*.value" => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            "{$field}.*.commissions.*.calculated_amount" => ['prohibited'],
            "{$field}.*.commissions.*.internal_note" => ['nullable', 'string', 'max:5000'],
            "{$field}.*.commissions.*.origin" => ['required', Rule::enum(CommissionOrigin::class)],
            "{$field}.*.commissions.*.commission_configuration_id" => ['nullable', 'integer', Rule::exists('commission_configurations', 'id')],
        ];
    }
}
