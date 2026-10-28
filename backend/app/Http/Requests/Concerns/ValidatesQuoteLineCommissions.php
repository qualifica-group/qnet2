<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Authorization\AuthorizationRegistry;
use App\Enums\CommissionOrigin;
use App\Enums\CommissionRecipientRole;
use App\Models\QuoteLine;
use App\Models\QuoteLineCommission;
use App\Models\User;
use App\Services\Commissions\CommissionRecipientResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

/**
 * The two write-side guards a quote's `offer_lines[].commissions` payload must
 * clear, split out of ValidatesQuoteLines (engineering.md §6) which keeps the
 * line SHAPE rules: what a commission may say (field permissions, spec 0004)
 * and whom it may pay (the recipient lock).
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesQuoteLineCommissions
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
        $snapshots = $this->supplierDirectionSnapshots($quote);

        foreach ((array) $this->input('offer_lines', []) as $lineIndex => $line) {
            if (! is_array($line) || ! array_key_exists('commissions', $line)) {
                continue;
            }

            if (! ($permissions['commissions']->editable ?? false)) {
                if ($this->commissionCollectionChanged($line, $existing, $snapshots)) {
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

                // Spec 0306, D-8: the direction of a manual Supplier commission
                // follows the `commission_type` permission; it is compared with
                // the line snapshot, so the FE resending it unchanged is no edit.
                if ($this->supplierDirectionChanged($line, $commission, $snapshots)
                    && ! ($permissions['commission_type']->editable ?? false)) {
                    $validator->errors()->add(
                        "offer_lines.{$lineIndex}.commissions.{$commissionIndex}.supplier_commission_direction",
                        'field not editable',
                    );
                }

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

    /**
     * Spec 0306, D-3: a manual Supplier commission must say its direction.
     * An automatic one takes the rule's, any other role has none.
     */
    protected function enforceSupplierCommissionDirection(Validator $validator): void
    {
        foreach ((array) $this->input('offer_lines', []) as $lineIndex => $line) {
            foreach ((array) data_get($line, 'commissions', []) as $commissionIndex => $commission) {
                if ($this->isManualSupplier($commission) && data_get($commission, 'supplier_commission_direction') === null) {
                    $validator->errors()->add(
                        "offer_lines.{$lineIndex}.commissions.{$commissionIndex}.supplier_commission_direction",
                        __('commission_configurations.direction_required'),
                    );
                }
            }
        }
    }

    private function isManualSupplier(mixed $commission): bool
    {
        return data_get($commission, 'recipient_role') === CommissionRecipientRole::Supplier->value
            && data_get($commission, 'origin') === CommissionOrigin::ManualOverride->value;
    }

    /** @return array<int, string|null> the frozen direction of each persisted line of the quote */
    private function supplierDirectionSnapshots(?Model $quote): array
    {
        return $quote === null
            ? []
            : QuoteLine::query()->where('quote_id', $quote->getKey())->get()
                ->mapWithKeys(fn (QuoteLine $line): array => [$line->id => $line->supplier_commission_direction?->value])
                ->all();
    }

    /** @param array<int, string|null> $snapshots */
    private function supplierDirectionChanged(array $line, mixed $commission, array $snapshots): bool
    {
        if (! $this->isManualSupplier($commission) || ! array_key_exists('supplier_commission_direction', (array) $commission)) {
            return false;
        }

        $persisted = isset($line['id']) ? ($snapshots[(int) $line['id']] ?? null) : null;

        return $persisted !== $commission['supplier_commission_direction'];
    }

    /**
     * A commission recipient is NOT free input: each role may only ever be
     * awarded to the one identity selected upstream (the quote's
     * commercial/reporter/supervisor, the line's supplier). A role
     * with no upstream selection admits no commission at all. The dialog locks
     * the field client-side against the same resolver; this is the server-side
     * half of that rule, so a stale or hand-rolled payload cannot pay someone
     * who was never chosen.
     */
    protected function enforceCommissionRecipients(Validator $validator, ?Model $quote): void
    {
        if (! $this->has('offer_lines')) {
            return;
        }

        $resolver = app(CommissionRecipientResolver::class);
        $commercialId = $this->effectiveRoleId('commercial_id', $quote?->commercial_id);
        $reporterId = $this->effectiveRoleId('reporter_id', $quote?->reporter_id);
        $supervisorId = $this->effectiveRoleId('supervisor_id', $quote?->supervisor_id);
        $persistedSuppliers = $this->persistedLineSuppliers($quote);

        foreach ((array) $this->input('offer_lines', []) as $lineIndex => $line) {
            if (! is_array($line) || ! is_array($line['commissions'] ?? null)) {
                continue;
            }

            $allowed = $resolver->resolve($this->effectiveLineSupplierId($line, $persistedSuppliers), $commercialId, $reporterId, $supervisorId);

            foreach ($line['commissions'] as $commissionIndex => $commission) {
                $role = is_array($commission) ? (string) ($commission['recipient_role'] ?? '') : '';

                if (! array_key_exists($role, $allowed)) {
                    // An unknown role is already reported by the enum rule.
                    continue;
                }

                $key = "offer_lines.{$lineIndex}.commissions.{$commissionIndex}";
                $recipient = $allowed[$role];

                if ($recipient === null) {
                    $validator->errors()->add(
                        "{$key}.recipient_role",
                        __('commission_configurations.role_without_recipient'),
                    );

                    continue;
                }

                if ((string) ($commission['recipient_type'] ?? '') !== $recipient->type
                    || (int) ($commission['recipient_id'] ?? 0) !== $recipient->id) {
                    $validator->errors()->add(
                        "{$key}.recipient_id",
                        __('commission_configurations.recipient_not_selected'),
                    );
                }
            }
        }
    }

    /**
     * The supplier the line will be saved with (spec 0320, D-7): the submitted
     * one, or the stored one when an untouched row omits the key (the writer's
     * own rule, QuoteLineWriter::sync()).
     *
     * @param  array<string, mixed>  $line
     * @param  array<int, array{product_id: int, supplier_id: int|null}>  $persisted
     */
    private function effectiveLineSupplierId(array $line, array $persisted): ?int
    {
        if (array_key_exists('supplier_id', $line)) {
            return is_numeric($line['supplier_id']) ? (int) $line['supplier_id'] : null;
        }

        $stored = isset($line['id']) && is_numeric($line['id']) ? ($persisted[(int) $line['id']] ?? null) : null;

        return $stored !== null && $stored['product_id'] === (int) ($line['product_id'] ?? 0) ? $stored['supplier_id'] : null;
    }

    /** @return array<int, array{product_id: int, supplier_id: int|null}> the quote's saved rows keyed by id */
    private function persistedLineSuppliers(?Model $quote): array
    {
        return $quote === null
            ? []
            : QuoteLine::query()->where('quote_id', $quote->getKey())->get(['id', 'product_id', 'supplier_id'])
                ->mapWithKeys(fn (QuoteLine $line): array => [$line->id => ['product_id' => $line->product_id, 'supplier_id' => $line->supplier_id]])
                ->all();
    }

    /** The role id the payload works against: the submitted one when present, the persisted one otherwise. */
    private function effectiveRoleId(string $field, ?int $persisted): ?int
    {
        if (! $this->has($field)) {
            return $persisted;
        }

        $submitted = $this->input($field);

        return $submitted === null ? null : (int) $submitted;
    }

    /** @param array<int, string|null> $snapshots */
    private function commissionCollectionChanged(array $line, Collection $existing, array $snapshots): bool
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
                    'supplier_commission_direction' => $commission->recipient_role === CommissionRecipientRole::Supplier && $commission->origin === CommissionOrigin::ManualOverride
                        ? ($snapshots[$lineId] ?? null)
                        : null,
                ])
                ->sortBy('recipient_role')
                ->values()
                ->all();
        $submitted = collect((array) ($line['commissions'] ?? []))
            ->map(fn (mixed $commission): array => [
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
                'supplier_commission_direction' => $this->isManualSupplier($commission)
                    ? data_get($commission, 'supplier_commission_direction')
                    : null,
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
}
