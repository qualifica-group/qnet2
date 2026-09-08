<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Services\RequestManagement\Report\Dashboard\DashboardChart;
use App\Services\RequestManagement\Report\Dashboard\DashboardPoint;
use App\Services\RequestManagement\Report\Dashboard\DashboardSummaryItem;
use App\Services\RequestManagement\Report\Dashboard\RequestManagementDashboardResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `{ summary, charts }` shape of spec 0107 data_contract. `applied` is
 * NOT part of this Resource — the controller adds it directly from the
 * validated request, it is not something RequestManagementDashboardBuilder
 * computes.
 *
 * @mixin RequestManagementDashboardResult
 */
class RequestManagementDashboardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RequestManagementDashboardResult $result */
        $result = $this->resource;

        return [
            'summary' => array_map($this->summaryItem(...), $result->summary),
            'charts' => array_map($this->chart(...), $result->charts),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryItem(DashboardSummaryItem $item): array
    {
        return ['key' => $item->key, 'label' => $item->label, 'value' => $item->value];
    }

    /**
     * @return array<string, mixed>
     */
    private function chart(DashboardChart $chart): array
    {
        return [
            'id' => $chart->id,
            'scope' => $chart->scope->value,
            'category_key' => $chart->categoryKey,
            'category_label' => $chart->categoryLabel,
            'indicator_key' => $chart->indicatorKey,
            'indicator_label' => $chart->indicatorLabel,
            'points' => array_map($this->point(...), $chart->points),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function point(DashboardPoint $point): array
    {
        return ['label' => $point->label, 'value' => $point->value];
    }
}
