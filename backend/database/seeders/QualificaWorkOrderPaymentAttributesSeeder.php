<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Models\Attribute;
use App\Models\ProductCategory;
use Database\Seeders\Concerns\SeedsCategoryAttributes;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user request 2026-11-11):
 * `php artisan db:seed --class=QualificaWorkOrderPaymentAttributesSeeder --force`.
 *
 * The legacy payment fields of the commessa ("Stato Pagamento", "Accordo sui
 * Pagamenti", "Insoluti") arrived assigned to the [OLD] categories alone, so a
 * commessa under a current category shows none of them. This makes every
 * category see them in the Commessa context: a root or a category with the
 * `inherits_work_order_attributes` barrier that does not own an attribute
 * gets its own assignment; every other category inherits it from its parent.
 *
 * Only ADDS, never creates an attribute (they come from the legacy catalogue)
 * and touches no layout: an unplaced field shows in "Altre informazioni".
 * Re-running it changes nothing.
 */
class QualificaWorkOrderPaymentAttributesSeeder extends Seeder
{
    use SeedsCategoryAttributes;

    /** @var list<string> */
    public const array ATTRIBUTE_CODES = ['stato_pagamento', 'accordo_pagamento', 'insoluti'];

    public function run(): void
    {
        DB::transaction(function (): void {
            // Step 1: the legacy attributes present in this installation.
            $attributes = $this->paymentAttributes();
            $categories = ProductCategory::query()->get();

            // Step 2: assign each attribute where it is neither owned nor inherited.
            foreach ($attributes as $attribute) {
                $assigned = $this->assignWhereMissing($attribute, $categories);
                $this->command?->info(sprintf('"%s": assegnato a %d categorie.', $attribute->name, $assigned));
            }
        });
    }

    /**
     * @return Collection<int, Attribute>
     */
    private function paymentAttributes(): Collection
    {
        $attributes = Attribute::query()->whereIn('code', self::ATTRIBUTE_CODES)->get();

        foreach (array_diff(self::ATTRIBUTE_CODES, $attributes->pluck('code')->all()) as $missing) {
            $this->command?->warn(sprintf('Attributo "%s" assente: saltato.', $missing));
        }

        return $attributes;
    }

    /**
     * Once every root sees the attribute, a child that inherits in the
     * Commessa context sees it through its parent by induction: only roots
     * and barrier categories that do not own it need an assignment.
     *
     * @param  Collection<int, ProductCategory>  $categories
     */
    private function assignWhereMissing(Attribute $attribute, Collection $categories): int
    {
        $owners = DB::table('attribute_category')
            ->where('attribute_id', $attribute->id)
            ->where('context', AttributeContext::WorkOrder->value)
            ->pluck('category_id')
            ->flip();
        $assigned = 0;

        foreach ($categories as $category) {
            $inherits = $category->parent_id !== null && $category->inheritsAttributesIn(AttributeContext::WorkOrder);

            if (! $inherits && ! $owners->has($category->id)) {
                $this->assignAttribute($category, $attribute, AttributeContext::WorkOrder);
                $assigned++;
            }
        }

        return $assigned;
    }
}
