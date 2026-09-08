<?php

namespace Database\Seeders\Concerns;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\AttributeLayout;

/**
 * Withdraws catalogue attributes from the categories an earlier revision of a
 * seeder assigned them to. The subtractive counterpart of
 * SeedsCategoryAttributes: without it, dropping a code from a catalogue — or
 * moving it to another context — is a no-op on every installation already
 * seeded, which keeps rendering the field exactly where it used to be.
 *
 * TWO PASSES, because the two places that can still surface a field are
 * independent:
 *   - the ASSIGNMENTS, so no category resolves the code any more and the form
 *     stops rendering it;
 *   - the persisted LAYOUT blobs, because a stale item is not merely invisible
 *     — AttributeLayoutMerger does drop it at render time, but
 *     AttributeLayoutValidator rejects a code outside the category's effective
 *     set on WRITE, so leaving it there would 422 the next save from the
 *     layout configurator.
 *
 * The attribute ROW itself is never deleted: a code may be shared with the
 * q-crm import that owns it, and deleting it would cascade the legacy history.
 * Unassigning is the exact inverse of what a catalogue did.
 */
trait RetiresAttributes
{
    /**
     * @param  list<string>  $codes
     * @param  AttributeContext|null  $context  the ONE context to withdraw
     *                                          from; null withdraws from every
     *                                          context, which is what "retired
     *                                          from the catalogue" means.
     */
    protected function retireAttributeCodes(array $codes, ?AttributeContext $context = null): void
    {
        $retired = Attribute::query()->whereIn('code', $codes)->get();

        if ($retired->isEmpty()) {
            return;
        }

        foreach ($retired as $attribute) {
            $assignments = $attribute->categories();

            if ($context !== null) {
                $assignments->wherePivot('context', $context->value);
            }

            $assignments->detach();
        }

        $this->stripFromLayouts($retired->pluck('code')->all(), $context);
    }

    /**
     * Rewrites the blob of every layout still placing one of $codes, pruning
     * the rows and sections left empty. Written straight onto the model rather
     * than through AttributeLayoutService::upsert(): that path validates the
     * WHOLE blob against the category's effective set, which is exactly what a
     * hand-configured layout may legitimately fail on for an unrelated reason —
     * and a retirement must never take a user's layout down with it.
     *
     * @param  list<string>  $codes
     */
    protected function stripFromLayouts(array $codes, ?AttributeContext $context = null): void
    {
        AttributeLayout::query()
            ->when($context !== null, fn ($query) => $query->where('context', $context->value))
            ->each(function (AttributeLayout $row) use ($codes): void {
                $sections = $this->withoutCodes($row->layout['sections'] ?? [], $codes);

                if ($sections === ($row->layout['sections'] ?? [])) {
                    return;
                }

                // An emptied layout means "back to flat" (AttributeLayoutService),
                // which is a deleted row, not a blob with zero sections.
                $sections === []
                    ? $row->delete()
                    : $row->update(['layout' => ['sections' => $sections]]);
            });
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @param  list<string>  $codes
     * @return array<int, array<string, mixed>>
     */
    private function withoutCodes(array $sections, array $codes): array
    {
        $pruned = array_map(static function (array $section) use ($codes): array {
            $rows = array_map(static function (array $row) use ($codes): array {
                $row['items'] = array_values(array_filter(
                    $row['items'] ?? [],
                    static fn (array $item): bool => ! in_array($item['attribute_code'] ?? null, $codes, true),
                ));

                return $row;
            }, $section['rows'] ?? []);

            $section['rows'] = array_values(array_filter($rows, static fn (array $row): bool => $row['items'] !== []));

            return $section;
        }, $sections);

        return array_values(array_filter($pruned, static fn (array $section): bool => $section['rows'] !== []));
    }
}
