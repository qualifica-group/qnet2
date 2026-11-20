<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\Attribute;
use App\Models\WorkOrder;
use App\Services\WorkOrders\WorkOrderAttributeValueWriter;
use App\Support\PeopleChangeLog;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabClass;

/**
 * Writes the 12 course fields (flexible attributes `gol_*`) of a work order
 * from a Formalab class. The only write path is WorkOrderAttributeValueWriter,
 * limited to the attributes applicable to the work order, with the same
 * activity log entry an app edit would leave.
 */
final class CourseFieldWriter
{
    /** The course field the work order must have for the integration to apply. */
    public const string ELIGIBILITY_CODE = 'gol_corso';

    /** The attribute codes this integration writes, in the order of values(). */
    public const array CODES = [
        'gol_corso', 'gol_stato_corso', 'gol_stato_classe', 'gol_data_inizio_corso', 'gol_data_fine_corso',
        'gol_ore_totali_corso', 'gol_data_esame', 'gol_data_ip_incasso', 'gol_anticipo_decreto_numero_data',
        'gol_anticipo_data_accredito', 'gol_saldo_decreto_numero_data', 'gol_saldo_data_accredito',
    ];

    public function __construct(
        private readonly WorkOrderAttributeResolver $resolver,
        private readonly WorkOrderAttributeValueWriter $writer,
    ) {}

    public function isEligible(WorkOrder $workOrder): bool
    {
        return $this->resolver->resolve($workOrder)->contains('code', self::ELIGIBILITY_CODE);
    }

    /**
     * The course field codes no attribute carries any more (renamed or
     * deleted in Configurazione > Attributi): those fields stop updating.
     *
     * @return list<string>
     */
    public function missingCodes(): array
    {
        $existing = Attribute::query()->whereIn('code', self::CODES)->pluck('code')->all();

        return array_values(array_diff(self::CODES, $existing));
    }

    /**
     * Attribute code => value, '' / missing already as null.
     *
     * @return array<string, mixed>
     */
    public function values(FormalabClass $class): array
    {
        return [
            'gol_corso' => $class->label,
            'gol_stato_corso' => $class->course_status,
            'gol_stato_classe' => $class->status,
            'gol_data_inizio_corso' => $class->start_date?->format('Y-m-d'),
            'gol_data_fine_corso' => $class->end_date?->format('Y-m-d'),
            'gol_ore_totali_corso' => $class->total_hours,
            'gol_data_esame' => $class->exam_date?->format('Y-m-d'),
            'gol_data_ip_incasso' => $class->ip_collection_date?->format('Y-m-d'),
            'gol_anticipo_decreto_numero_data' => $class->advance_decree,
            'gol_anticipo_data_accredito' => $class->advance_credited_at?->format('Y-m-d'),
            'gol_saldo_decreto_numero_data' => $class->balance_decree,
            'gol_saldo_data_accredito' => $class->balance_credited_at?->format('Y-m-d'),
        ];
    }

    /**
     * @return bool whether any value of the work order actually changed
     *
     * @throws ValidationException a value is refused by the attribute rules
     */
    public function apply(WorkOrder $workOrder, FormalabClass $class): bool
    {
        // Step 1: only the course fields applicable to this work order, without
        // an empty value for a field that is already empty (not a change)
        $applicable = $this->resolver->resolve($workOrder);
        $current = $workOrder->attribute_values ?? [];
        $submitted = array_filter(
            array_intersect_key($this->values($class), array_flip($applicable->pluck('code')->all())),
            static fn (mixed $value, string $code): bool => $value !== null || ($current[$code] ?? null) !== null,
            ARRAY_FILTER_USE_BOTH,
        );

        if ($submitted === []) {
            return false;
        }

        // Step 2: write through the shared pipeline and leave the audit entry
        $captured = PeopleChangeLog::capture($workOrder);
        $changed = [];
        $old = [];
        $this->writer->apply($workOrder, $submitted, $changed, $old, $applicable);
        $workOrder->save();
        PeopleChangeLog::log($captured);

        return $changed !== [];
    }
}
