<?php

declare(strict_types=1);

namespace App\Services\WorkOrders;

use App\Enums\FormMode;
use App\Models\Quote;
use App\Models\QuoteLine;
use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\RequestManagement\AttributeValueValidator;
use App\Support\PeopleChangeLog;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Copies the offer's flexible fields onto a work order (spec 0404 part D): a
 * code of the work order's applicable set that is still empty takes the first
 * non-empty value the offers carry under the same code, in the given order
 * (D-D1, multi-offer: the order of the selected contracts). It only fills,
 * never overwrites (D-D2); a value the work order would reject (an option
 * that does not exist there) is skipped (D-D3). Used right after a work
 * order is generated from a contract and by the "Copia dati dall'offerta"
 * action.
 */
final class WorkOrderQuoteAttributeCopier
{
    private const string LINE_PIVOT = 'quote_line_work_order';

    public function __construct(
        private readonly WorkOrderAttributeResolver $attributeResolver,
        private readonly AttributeValueValidator $validator,
        private readonly WorkOrderAttributeValueWriter $writer,
    ) {}

    /**
     * Fills the empty fields of $workOrder from $quoteIds and returns how many
     * were copied. $keepCodes are left alone even when empty: the values the
     * user sent explicitly win (D-D1).
     *
     * @param  array<int, int>  $quoteIds
     * @param  array<int, string>  $keepCodes
     */
    public function fill(WorkOrder $workOrder, array $quoteIds, array $keepCodes = []): int
    {
        // Step 1: the codes still to fill and the offers' values for them
        $applicable = $this->attributeResolver->resolve($workOrder)->keyBy('code');
        $copied = $this->valuesToCopy($workOrder, $applicable, $quoteIds, $keepCodes);

        if ($copied === []) {
            return 0;
        }

        // Step 2: the same write as the form, logged like a form save
        $before = PeopleChangeLog::capture($workOrder);
        $changed = [];
        $old = [];
        $this->writer->apply($workOrder, $copied, $changed, $old, $applicable->values(), $this->attributeResolver->layout($workOrder, FormMode::View));
        $workOrder->save();
        PeopleChangeLog::log($before);

        return count($copied);
    }

    /**
     * The offers a work order's fields come from: its own, or (multi-offer
     * work order) the offers of its lines, oldest first.
     *
     * @return array<int, int>
     */
    public function quoteIdsOf(WorkOrder $workOrder): array
    {
        if ($workOrder->quote_id !== null) {
            return [$workOrder->quote_id];
        }

        return QuoteLine::query()
            ->whereIn('id', DB::table(self::LINE_PIVOT)->where('work_order_id', $workOrder->id)->select('quote_line_id'))
            ->distinct()
            ->orderBy('quote_id')
            ->pluck('quote_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $applicable
     * @param  array<int, int>  $quoteIds
     * @param  array<int, string>  $keepCodes
     * @return array<string, mixed>
     */
    private function valuesToCopy(WorkOrder $workOrder, Collection $applicable, array $quoteIds, array $keepCodes): array
    {
        $current = $workOrder->attribute_values ?? [];
        $quoteValues = Quote::query()->whereIn('id', $quoteIds)->pluck('attribute_values', 'id');
        $copied = [];

        foreach ($applicable->keys() as $code) {
            if (in_array($code, $keepCodes, true) || self::isFilled($current[$code] ?? null)) {
                continue;
            }

            foreach ($quoteIds as $quoteId) {
                $value = ($quoteValues->get($quoteId) ?? [])[$code] ?? null;

                if (self::isFilled($value) && $this->isValid($applicable, $code, $value)) {
                    $copied[$code] = $value;
                    break;
                }
            }
        }

        return $copied;
    }

    /**
     * @param  Collection<string, ApplicableAttribute>  $applicable
     */
    private function isValid(Collection $applicable, string $code, mixed $value): bool
    {
        try {
            $this->validator->validate($applicable->only([$code])->values(), [$code => $value]);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Empty = no value at all, a blank string, an empty list, or a table
     * field without rows.
     */
    private static function isFilled(mixed $value): bool
    {
        if ($value === null || $value === '' || $value === []) {
            return false;
        }

        return ! (is_array($value) && array_key_exists('rows', $value) && $value['rows'] === []);
    }
}
