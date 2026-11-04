<?php

namespace App\Jobs;

use App\Services\ChartOfAccounts\RevenueLineClassifier;
use App\Services\ChartOfAccounts\RevenueTypeResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Classifies the still unclassified invoice lines after a product or a
 * product category got (or changed) its revenue sub-account (spec 0350,
 * D-9b). Targets either explicit products, or the products that take their
 * sub-account from a category (resolved when the job runs, so it sees the
 * tree as saved). Idempotent: a line that already has a value is never
 * touched.
 */
class ClassifyRevenueLinesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(
        private readonly array $productIds = [],
        private readonly ?int $categoryId = null,
    ) {}

    public function handle(RevenueLineClassifier $classifier, RevenueTypeResolver $resolver): void
    {
        $productIds = $this->categoryId === null ? $this->productIds : $resolver->productIdsInheritingFrom($this->categoryId);

        foreach (array_chunk($productIds, 1000) as $chunk) {
            $classifier->classifyProducts($chunk);
        }
    }
}
