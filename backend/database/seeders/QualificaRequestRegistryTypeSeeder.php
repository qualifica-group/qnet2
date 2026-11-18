<?php

namespace Database\Seeders;

use App\Enums\PersonalDataTypeEnum;
use App\Models\ProductCategory;
use App\Services\ProductCategories\RequestRegistryTypeInheritance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot "Anagrafica in Gestione Richieste" catalogue setup (spec 0410,
 * user directive 2026-11-18): the Formazione and APL branches start the
 * new-client card on a natural person, every other branch on a company.
 *
 * The legacy roots follow the current root they point to
 * (`current_category_id`, spec 0374): "[OLD] FORMAZIONE" and "[OLD] APL" stay
 * natural person like their current roots. Each root is written and then its
 * subtree is realigned by RequestRegistryTypeInheritance, the setting's only
 * writer, so the root-owned invariant holds.
 *
 * `php artisan db:seed --class=QualificaRequestRegistryTypeSeeder`. One
 * transaction; a re-run converges.
 */
class QualificaRequestRegistryTypeSeeder extends Seeder
{
    /** Names of the current roots whose branches are worked with natural persons. */
    public const array INDIVIDUAL_ROOT_NAMES = ['Formazione', 'APL'];

    public function __construct(private readonly RequestRegistryTypeInheritance $inheritance) {}

    public function run(): void
    {
        $roots = ProductCategory::query()->whereNull('parent_id')->orderBy('id')->get();

        DB::transaction(function () use ($roots): void {
            // Step 1: the current roots of the natural-person branches.
            $individualRootIds = $this->individualRootIds($roots);

            // Step 2: every root and its subtree on the resolved type.
            $changed = 0;
            foreach ($roots as $root) {
                $type = $this->typeFor($root, $individualRootIds);
                if ($root->request_registry_type !== $type) {
                    $root->update(['request_registry_type' => $type]);
                    $changed++;
                }
                $this->inheritance->syncSubtree($root);
            }

            $this->command?->info(sprintf(
                'Request registry type: %d roots changed, %d natural-person roots.',
                $changed,
                $roots->filter(fn (ProductCategory $root): bool => $this->typeFor($root, $individualRootIds) === PersonalDataTypeEnum::Individual)->count(),
            ));
        });
    }

    /**
     * @param  Collection<int, ProductCategory>  $roots
     * @return array<int, int>
     */
    private function individualRootIds(Collection $roots): array
    {
        $names = array_map('mb_strtolower', self::INDIVIDUAL_ROOT_NAMES);

        return $roots
            ->filter(fn (ProductCategory $root): bool => ! $root->is_legacy && in_array(mb_strtolower(trim($root->name)), $names, true))
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, int>  $individualRootIds
     */
    private function typeFor(ProductCategory $root, array $individualRootIds): PersonalDataTypeEnum
    {
        $currentRootId = $root->is_legacy ? $root->current_category_id : $root->id;

        return in_array($currentRootId, $individualRootIds, true)
            ? PersonalDataTypeEnum::Individual
            : PersonalDataTypeEnum::Company;
    }
}
