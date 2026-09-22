<?php

use App\Http\Controllers\WorkOrders\WorkOrderStageController;
use App\Http\Controllers\WorkOrders\WorkOrderTaskBoardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Work Order Task Board / "Fasi" (spec 0146)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php from the start (file-size split,
| engineering.md §6), mirroring routes/api/work-orders.php and
| routes/api/tasks.php. Required from routes/api.php INSIDE the existing
| `auth:sanctum` group, so every route below inherits that same
| middleware/prefix context. No throttle anywhere: project rule
| (backend.md §2, user decision 2026-07-15).
|
| stages/reorder is declared ABOVE stages/{stage} so the literal segment
| wins over the route-model-binding wildcard (mirrors work-orders/next-code).
| Every stages/{stage} route is `->scopeBindings()`: WorkOrder::stages() is
| the relation Laravel's nested binding resolves through, so a {stage} from
| another commessa 404s before the controller ever runs (constraints,
| AC-007) — the one 404 this module produces on its own, everything else is
| 403/409/422 from the Service layer.
|
| Authorization is enforced server-side in the two controllers via
| WorkOrderPolicy/TaskPolicy on every endpoint (D-9), never here.
*/

Route::get('work-orders/{workOrder}/task-board', [WorkOrderTaskBoardController::class, 'show']);
Route::post('work-orders/{workOrder}/task-board/move', [WorkOrderTaskBoardController::class, 'move']);
Route::post('work-orders/{workOrder}/task-board/bulk', [WorkOrderTaskBoardController::class, 'bulk']);

Route::get('work-orders/{workOrder}/stages', [WorkOrderStageController::class, 'index']);
Route::post('work-orders/{workOrder}/stages', [WorkOrderStageController::class, 'store']);
Route::post('work-orders/{workOrder}/stages/reorder', [WorkOrderStageController::class, 'reorder']);

Route::match(['put', 'patch'], 'work-orders/{workOrder}/stages/{stage}', [WorkOrderStageController::class, 'update'])
    ->scopeBindings();
Route::delete('work-orders/{workOrder}/stages/{stage}', [WorkOrderStageController::class, 'destroy'])
    ->scopeBindings();
Route::post('work-orders/{workOrder}/stages/{stage}/close', [WorkOrderStageController::class, 'close'])
    ->scopeBindings();
Route::post('work-orders/{workOrder}/stages/{stage}/reopen', [WorkOrderStageController::class, 'reopen'])
    ->scopeBindings();
