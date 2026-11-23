<?php

declare(strict_types=1);

use App\Models\WorkOrder;
use App\Models\WorkOrderPage;

if (! function_exists('pageUrl')) {
    function pageUrl(WorkOrder $workOrder, string $suffix = ''): string
    {
        return "/api/work-orders/{$workOrder->id}/page".($suffix === '' ? '' : "/{$suffix}");
    }
}

if (! function_exists('pagePngDataUri')) {
    /** A valid 1x1 PNG as a data: URI. */
    function pagePngDataUri(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';
    }
}

if (! function_exists('pageFor')) {
    /**
     * @param  array<string, mixed>  $attributes
     */
    function pageFor(WorkOrder $workOrder, array $attributes = []): WorkOrderPage
    {
        return WorkOrderPage::factory()->create(['work_order_id' => $workOrder->id, ...$attributes]);
    }
}
