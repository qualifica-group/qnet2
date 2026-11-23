<?php

declare(strict_types=1);

namespace Integrations\Formalab\Services;

use App\Models\Attribute;
use App\Models\WorkOrder;
use App\RequestManagement\ApplicableAttribute;
use App\Services\WorkOrders\WorkOrderAttributeValueWriter;
use App\Support\PeopleChangeLog;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Integrations\Formalab\Models\FormalabClass;
use Integrations\Formalab\Support\FormalabField;

/**
 * Writes a Formalab class onto a work order through the field map of its
 * categories (spec 0423): only the mapped attributes applicable to the work
 * order, through WorkOrderAttributeValueWriter, with the same activity log
 * entry an app edit would leave.
 */
final class CourseFieldWriter
{
    public function __construct(
        private readonly WorkOrderAttributeResolver $resolver,
        private readonly WorkOrderAttributeValueWriter $writer,
        private readonly CategoryFieldMapResolver $maps,
    ) {}

    /** Eligible = the attribute mapped on the class (`label`) is applicable to the work order. */
    public function isEligible(WorkOrder $workOrder): bool
    {
        return $this->applicableMap($workOrder) !== [];
    }

    /**
     * The edition chosen in the work order's class select (the attribute
     * mapped on `label`, spec 0426); null when empty, not an id, or not eligible.
     */
    public function editionIdOf(WorkOrder $workOrder): ?int
    {
        $code = $this->keyCodeOf($workOrder);
        $value = $code === null ? null : ($workOrder->attribute_values[$code] ?? null);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /** The code of the work order's class select (the attribute mapped on `label`); null when not eligible. */
    public function keyCodeOf(WorkOrder $workOrder): ?string
    {
        return $this->applicableMap($workOrder)[FormalabField::Label->value] ?? null;
    }

    /** How many distinct attribute codes the saved category maps use. */
    public function mappedCodesCount(): int
    {
        return count($this->maps->mappedCodes());
    }

    /**
     * The mapped attribute codes no attribute carries any more (renamed or
     * deleted in Configurazione > Attributi): those fields stop updating.
     *
     * @return list<string>
     */
    public function missingCodes(): array
    {
        $mapped = $this->maps->mappedCodes();
        $existing = Attribute::query()->whereIn('code', $mapped)->pluck('code')->all();

        return array_values(array_diff($mapped, $existing));
    }

    /**
     * @return bool whether any value of the work order actually changed
     *
     * @throws ValidationException the work order is not eligible, or a value is refused by the attribute rules
     */
    public function apply(WorkOrder $workOrder, FormalabClass $class): bool
    {
        // Step 1: the map of its linked categories, limited to the applicable attributes
        $applicable = $this->resolver->resolve($workOrder);
        $map = $this->applicableMap($workOrder, $applicable);

        if ($map === []) {
            throw ValidationException::withMessages(['work_order' => __('formalab::messages.not_eligible')]);
        }

        // Step 2: the class values, without an empty value for a field that is already empty (not a change)
        $current = $workOrder->attribute_values ?? [];
        $submitted = [];

        foreach ($map as $field => $code) {
            $value = FormalabField::from($field)->valueFrom($class);

            if ($value !== null || ($current[$code] ?? null) !== null) {
                $submitted[$code] = $value;
            }
        }

        if ($submitted === []) {
            return false;
        }

        // Step 3: write through the shared pipeline and leave the audit entry
        $captured = PeopleChangeLog::capture($workOrder);
        $changed = [];
        $old = [];
        $this->writer->apply($workOrder, $submitted, $changed, $old, $applicable);
        $workOrder->save();
        PeopleChangeLog::log($captured);

        return $changed !== [];
    }

    /**
     * The work order's map restricted to its applicable attributes; empty
     * when the class select is not among them (the edition cannot be found).
     *
     * The applicable attributes are resolved only when a linked category
     * maps the key, so a work order outside Formalab costs one map lookup.
     *
     * @param  Collection<int, ApplicableAttribute>|null  $applicable  null = resolve them here
     * @return array<string, string> formalab_field => attribute_code
     */
    private function applicableMap(WorkOrder $workOrder, ?Collection $applicable = null): array
    {
        $map = $this->maps->forWorkOrder($workOrder);

        if (! isset($map[FormalabField::Label->value])) {
            return [];
        }

        $codes = ($applicable ?? $this->resolver->resolve($workOrder))->pluck('code')->all();
        $map = array_filter($map, static fn (string $code): bool => in_array($code, $codes, true));

        return isset($map[FormalabField::Label->value]) ? $map : [];
    }
}
