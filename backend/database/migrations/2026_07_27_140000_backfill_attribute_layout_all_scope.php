<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Backfill for the shared-scope model (spec 0062, D3 revised): until now a
 * category that had configured a SINGLE (context, form_mode) row relied on
 * an implicit create -> edit -> view fallback to drive the other modes. That
 * implicit fallback is replaced by the explicit `all` scope, so every such
 * lone row becomes the shared layout — preserving exactly the rendering
 * those categories already had. A context with more than one row was
 * genuinely per-mode and is left untouched.
 *
 * Literal 'all'/'create' values (not App\Enums\LayoutFormScope) so this
 * migration keeps replaying identically if the enum ever changes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->groupedByCategoryAndContext()
            ->filter(static fn ($rows): bool => $rows->count() === 1)
            ->each(function ($rows): void {
                DB::table('attribute_layouts')
                    ->where('id', $rows->first()->id)
                    ->update(['form_mode' => 'all']);
            });
    }

    /**
     * Reverses to the pre-scope world, where 'create' was the head of the
     * implicit fallback order: a shared row becomes the category's `create`
     * row, unless one already exists for that context — the unique index
     * forbids two, and the explicit per-mode row is the one worth keeping.
     */
    public function down(): void
    {
        $this->groupedByCategoryAndContext()->each(function ($rows): void {
            $shared = $rows->firstWhere('form_mode', 'all');

            if ($shared === null) {
                return;
            }

            $query = DB::table('attribute_layouts')->where('id', $shared->id);

            $rows->contains(fn ($row): bool => $row->form_mode === 'create')
                ? $query->delete()
                : $query->update(['form_mode' => 'create']);
        });
    }

    /**
     * @return Collection<string, Collection<int, object>>
     */
    private function groupedByCategoryAndContext(): Collection
    {
        return DB::table('attribute_layouts')
            ->select('id', 'product_category_id', 'context', 'form_mode')
            ->get()
            ->groupBy(static fn ($row): string => $row->product_category_id.'|'.$row->context);
    }
};
