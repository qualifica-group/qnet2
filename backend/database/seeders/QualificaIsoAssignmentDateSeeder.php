<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Migrations\Realign\LegacyRealignClient;
use App\Models\ProductCategory;
use App\Models\WorkOrder;
use App\Services\ProductCategories\CategoryHierarchy;
use App\WorkOrders\WorkOrderAttributeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

/**
 * One-shot (user request 2026-11-27): `php artisan db:seed --class=QualificaIsoAssignmentDateSeeder --force`.
 *
 * Every commessa of "ISO" and "[OLD] ISO" (subcategories included) with a Responsabile but no
 * "Data di assegnazione": the legacy wrote that date only from 2024-10-29 and only when a
 * technician was added from the commessa (AddTecnico, Detail::storeTecnico), never from the
 * contract planning nor before that day, so it was empty on 1,751 migrated commesse. The date
 * is the legacy's `assignment_date` (its own date or, when missing, the day of the last
 * technician added).
 *
 * Writes only where the date is missing, never changes one; quiet write (no activity entry,
 * no notification, `updated_at` unchanged). Needs the legacy migration API. Re-running it
 * changes nothing.
 */
class QualificaIsoAssignmentDateSeeder extends Seeder
{
    private const array ROOTS = ['ISO', '[OLD] ISO'];

    private const string SOURCE = 'work-orders';

    private const int LEGACY_TIMEOUT_SECONDS = 120;

    public function __construct(
        private readonly CategoryHierarchy $hierarchy,
        private readonly WorkOrderAttributeResolver $attributeResolver,
    ) {}

    public function run(): void
    {
        // Step 1: a legacy page of 100 records is slower than one import page; the client reads it when built.
        config(['migrations.timeout' => self::LEGACY_TIMEOUT_SECONDS]);
        $client = app(LegacyRealignClient::class);
        $written = 0;

        // Step 2: the commesse in scope, a legacy page per chunk.
        $this->scope()->chunkById(LegacyRealignClient::MAX_IDS_PER_REQUEST, function (Collection $workOrders) use ($client, &$written): void {
            $missing = $workOrders->mapWithKeys(fn (WorkOrder $workOrder): array => [$workOrder->id => $this->missingDateCodes($workOrder)])->filter();
            $dates = $this->legacyDates($client, $workOrders->whereIn('id', $missing->keys())->pluck('old_id')->filter()->all());

            // Step 3: the date where it is missing.
            foreach ($workOrders->whereIn('id', $missing->keys()) as $workOrder) {
                $date = $dates[(string) $workOrder->old_id] ?? null;

                if ($date === null) {
                    $this->command?->warn("{$workOrder->code}: no assignment date from the legacy, left empty.");

                    continue;
                }

                $this->write($workOrder, $missing[$workOrder->id], $date);
                $written++;
            }
        });

        $this->command?->info("Assignment date written on {$written} commesse.");
    }

    /**
     * @return Builder<WorkOrder>
     */
    private function scope(): Builder
    {
        $roots = ProductCategory::query()->whereNull('parent_id')->whereIn('name', self::ROOTS)->pluck('id')->all();
        $categoryIds = collect($roots)->flatMap(fn (int $id): array => [$id, ...$this->hierarchy->descendantIds($id)])->all();

        return WorkOrder::query()
            ->whereHas('quoteLines.product', static fn (Builder $query) => $query->whereIn('category_id', $categoryIds))
            ->whereHas('supervisors');
    }

    /**
     * The assignment-date codes the commessa carries, when none of them is filled.
     *
     * @return list<string>
     */
    private function missingDateCodes(WorkOrder $workOrder): array
    {
        $applicable = $this->attributeResolver->resolve($workOrder)->pluck('code')->all();
        $codes = array_values(array_intersect((array) config('work-orders.assignment_date_attribute_codes'), $applicable));
        $values = $workOrder->attribute_values ?? [];

        return collect($codes)->contains(static fn (string $code): bool => filled($values[$code] ?? null)) ? [] : $codes;
    }

    /**
     * @param  array<int, int|string>  $legacyIds
     * @return array<string, string> legacy id => assignment date
     */
    private function legacyDates(LegacyRealignClient $client, array $legacyIds): array
    {
        if ($legacyIds === []) {
            return [];
        }

        return collect($client->records(self::SOURCE, array_map(strval(...), array_values($legacyIds))))
            ->filter(static fn (array $legacy): bool => is_string($legacy['assignment_date'] ?? null) && $legacy['assignment_date'] !== '')
            ->mapWithKeys(static fn (array $legacy): array => [(string) $legacy['id'] => $legacy['assignment_date']])
            ->all();
    }

    /**
     * @param  list<string>  $codes
     */
    private function write(WorkOrder $workOrder, array $codes, string $date): void
    {
        activity()->withoutLogs(static function () use ($workOrder, $codes, $date): void {
            $workOrder->forceFill(['attribute_values' => [...($workOrder->attribute_values ?? []), ...array_fill_keys($codes, $date)]]);
            $workOrder->timestamps = false;
            $workOrder->saveQuietly();
        });
    }
}
