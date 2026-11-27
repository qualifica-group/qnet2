<?php

namespace Database\Seeders;

use App\Enums\AttributeContext;
use App\Models\ProductCategory;
use App\Services\ProductCategories\AttributeLayoutPruner;
use App\Services\ProductCategories\CategoryHierarchy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * One-shot (user directives 2026-11-27, spec 0189 G-20): fields leave the
 * Commessa of "[OLD] ISO" and of every category under it: the "Dati storici
 * ISO" section with its 11 legacy `orderisos` fields, and the flexible "Stato
 * Lavorazione" of the ISO flow (`stato_consulenza_1`, in "Dettagli Lavorazioni
 * Iso"), a frozen copy of the work order status that qnet-2 keeps on its own.
 * The fields leave the Commessa of those categories, so they do not fall into
 * "Altre informazioni"; their values stay in the work orders'
 * `attribute_values`, and the fields stay wherever else they are assigned
 * ([OLD] PROGETTI-).
 *
 * Writes through the query builder: no activity log, no notification.
 * Idempotent: a second run finds nothing to detach or prune.
 * Run with `php artisan db:seed --class=QualificaOldIsoWorkOrderFieldsRemovalSeeder --force`.
 */
class QualificaOldIsoWorkOrderFieldsRemovalSeeder extends Seeder
{
    public const string ROOT = '[OLD] ISO';

    /** The flexible "Stato Lavorazione" of the ISO flow (legacy root 1). */
    public const string STATE_CODE = 'stato_consulenza_1';

    /** The fields of the "Dati storici ISO" section, then the state. */
    public const array CODES = [
        'settore',
        'ente_ultimo_audit',
        'data_emissione_certificato',
        'data_scadenza_certificato',
        'data_ente_primo_audit',
        'data_ente_ultimo_audit',
        'data_ente_prossimo_audit',
        'data_vi',
        'ispettore_vi',
        'sup_vi',
        'data_ultima_chiamata',
        self::STATE_CODE,
    ];

    public function __construct(private readonly CategoryHierarchy $hierarchy) {}

    public function run(): void
    {
        $root = ProductCategory::query()->where('name', self::ROOT)->whereNull('parent_id')->firstOrFail();
        $categoryIds = [$root->id, ...$this->hierarchy->descendantIds($root->id)];

        DB::transaction(function () use ($categoryIds): void {
            // Step 1: the fields out of the Commessa of the tree, values kept
            $detached = DB::table('attribute_category')
                ->whereIn('category_id', $categoryIds)
                ->where('context', AttributeContext::WorkOrder->value)
                ->whereIn('attribute_id', DB::table('attributes')->whereIn('code', self::CODES)->select('id'))
                ->delete();

            // Step 2: their places out of the Commessa forms, the emptied section with them
            $layouts = $this->pruneForms($categoryIds);

            $this->command?->info("[OLD] ISO: {$detached} assegnazioni tolte, {$layouts} form ripuliti.");
        });
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function pruneForms(array $categoryIds): int
    {
        $pruned = 0;
        $keep = static fn (?string $code): bool => ! in_array($code, self::CODES, true);

        $layouts = DB::table('attribute_layouts')
            ->whereIn('product_category_id', $categoryIds)
            ->where('context', AttributeContext::WorkOrder->value)
            ->get(['id', 'layout']);

        foreach ($layouts as $row) {
            $layout = json_decode((string) $row->layout, true);
            $layout = is_array($layout) ? $layout : [];
            // Keys beside `sections` (canonical_order) stay as they are.
            $kept = [...$layout, ...AttributeLayoutPruner::prune($layout, $keep)];

            if ($kept === $layout) {
                continue;
            }

            DB::table('attribute_layouts')->where('id', $row->id)->update(['layout' => json_encode($kept, JSON_UNESCAPED_UNICODE)]);
            $pruned++;
        }

        return $pruned;
    }
}
