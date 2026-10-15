<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\ProductCategory;

/**
 * Renames, in place, the catalogue nodes whose name changed after an
 * installation was seeded (user directive 2026-10-15: the SOA and Sicurezza
 * subcategories carry their branch as prefix). The name is the catalogue's
 * natural key, so without this a re-run would seed the new name as a second,
 * empty node beside the old one; renaming keeps the id, hence the products
 * (found by name and category), the deals and every other reference.
 *
 * A no-op on a fresh install and once applied. When the new name already
 * exists the old node is left alone: merging two live nodes is not a rename.
 */
final class CatalogCategoryRenames
{
    /**
     * Branch root => (former name => current name). Scoped to the root's own
     * children: "Documenti" or "Trasferta" may well name a node elsewhere.
     *
     * @var array<string, array<string, string>>
     */
    private const array RENAMES = [
        SoaCatalogue::ROOT => [
            'Ente SOA' => SoaCatalogue::CERTIFYING_BODY_CATEGORY,
            'Consulenza SOA' => SoaCatalogue::CONSULTING_CATEGORY,
        ],
        SafetyCatalogue::ROOT => [
            'Corsi di formazione' => SafetyCatalogue::TRAINING_COURSES,
            'Documenti' => SafetyCatalogue::DOCUMENTS,
            'Visite mediche' => SafetyCatalogue::MEDICAL_VISITS,
            'Sopralluogo' => SafetyCatalogue::SITE_INSPECTIONS,
            'Trasferta' => SafetyCatalogue::TRAVEL,
        ],
    ];

    public function apply(): void
    {
        foreach (self::RENAMES as $rootName => $renames) {
            $root = ProductCategory::query()->where('name', $rootName)->whereNull('parent_id')->first();

            if ($root !== null) {
                $this->renameChildren($root, $renames);
            }
        }
    }

    /**
     * @param  array<string, string>  $renames
     */
    private function renameChildren(ProductCategory $root, array $renames): void
    {
        $taken = ProductCategory::query()->whereIn('name', array_values($renames))->pluck('name')->flip();
        $children = ProductCategory::query()
            ->where('parent_id', $root->id)
            ->whereIn('name', array_keys($renames))
            ->get();

        foreach ($children as $child) {
            if (! isset($taken[$renames[$child->name]])) {
                $child->update(['name' => $renames[$child->name]]);
            }
        }
    }
}
