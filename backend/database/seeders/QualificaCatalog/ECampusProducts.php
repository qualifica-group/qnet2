<?php

namespace Database\Seeders\QualificaCatalog;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\ProductService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brings the e-Campus products an earlier revision seeded in line with
 * ECampusCourseCatalogue, before CatalogProducts files the current ones.
 * Split out of CatalogProducts (engineering.md §6): the upsert stays there,
 * the destructive maintenance lives here.
 *
 * Runs before the upsert on purpose: a product still named the old way would
 * miss the (name, category) key and be seeded a second time.
 */
final class ECampusProducts
{
    /**
     * Every column holding a product reference that restricts its delete:
     * table => column.
     *
     * @var array<string, string>
     */
    private const array PRODUCT_REFERENCES = [
        'quote_lines' => 'product_id',
        'opportunity_product' => 'product_id',
        'lead_product' => 'product_id',
        'commission_configurations' => 'product_id',
    ];

    /** "[LM-56] Scienze dell'Economia": the code, then the course. */
    private const string LEADING_CODE = '/^(\[[^\]]+\]) (.+)$/';

    public function __construct(private readonly ProductService $products) {}

    public function realign(ProductCategory $branch): void
    {
        // Step 1: the trailing-code names take the leading code.
        $this->renameLegacyProducts($branch);
        // Step 2: the fees no longer sold per course go.
        $this->retireFees($branch);
    }

    /**
     * Renames "<course> [CODE] <fee>" to "[CODE] <course> <fee>" (user
     * directive 2026-10-08), so its id, code and every record pointing at it
     * survive. A product whose new name is already taken is left alone and
     * logged: merging two products is an operator's call.
     */
    private function renameLegacyProducts(ProductCategory $branch): void
    {
        foreach (ECampusCourseCatalogue::DEGREES as $degree) {
            $fees = [...array_keys($degree['fees']), ...ECampusCourseCatalogue::RETIRED_FEES];

            foreach (array_keys($degree['courses']) as $course) {
                $legacyCourse = preg_replace(self::LEADING_CODE, '$2 $1', $course);

                foreach ($fees as $fee) {
                    $this->rename($branch, sprintf('%s %s', $legacyCourse, $fee), sprintf('%s %s', $course, $fee));
                }
            }
        }
    }

    private function rename(ProductCategory $branch, string $from, string $to): void
    {
        $product = Product::query()->where('category_id', $branch->id)->where('name', $from)->first();

        if ($product === null) {
            return;
        }

        if (Product::query()->where('category_id', $branch->id)->where('name', $to)->exists()) {
            Log::warning('Legacy e-Campus product kept: its new name is already taken.', ['product_id' => $product->id, 'name' => $product->name]);

            return;
        }

        $product->update(['name' => $to]);
    }

    /**
     * Deletes the product of every retired fee of every e-Campus course. A
     * product already referenced — an offer line, a product of interest, a
     * commission rule — stays: deleting it would break that record, so it is
     * logged for an operator to handle instead.
     */
    private function retireFees(ProductCategory $branch): void
    {
        $names = [];

        foreach (ECampusCourseCatalogue::DEGREES as $degree) {
            foreach (array_keys($degree['courses']) as $course) {
                foreach (ECampusCourseCatalogue::RETIRED_FEES as $fee) {
                    $names[] = sprintf('%s %s', $course, $fee);
                }
            }
        }

        Product::query()
            ->where('category_id', $branch->id)
            ->whereIn('name', $names)
            ->each(function (Product $product): void {
                $this->isReferenced($product)
                    ? Log::warning('Retired e-Campus product kept: it is still referenced.', ['product_id' => $product->id, 'name' => $product->name])
                    : $this->products->delete($product);
            });
    }

    private function isReferenced(Product $product): bool
    {
        foreach (self::PRODUCT_REFERENCES as $table => $column) {
            if (DB::table($table)->where($column, $product->id)->exists()) {
                return true;
            }
        }

        return false;
    }
}
