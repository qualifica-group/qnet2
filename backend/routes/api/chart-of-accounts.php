<?php

use App\Http\Controllers\ChartOfAccounts\ChartAccountController;
use App\Http\Controllers\ChartOfAccounts\ChartOfAccountsTreeController;
use App\Http\Controllers\ChartOfAccounts\RevenueTypeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Chart of accounts (spec 0348)
|--------------------------------------------------------------------------
|
| Managerial three-level chart (group > account > sub-account). Groups and
| accounts live under `chart-accounts`, revenue sub-accounts under
| `revenue-types`; cost sub-accounts stay under `cost-types`. The tree is a
| dedicated endpoint (D-12), not a TableDefinition. Authorization is enforced
| server-side in each controller via the resource policies. Required from
| routes/api/accounting.php INSIDE the `auth:sanctum` group.
*/

Route::get('chart-of-accounts/tree', ChartOfAccountsTreeController::class);

Route::get('chart-accounts/{chartAccount}', [ChartAccountController::class, 'show']);
Route::post('chart-accounts', [ChartAccountController::class, 'store']);
Route::match(['put', 'patch'], 'chart-accounts/{chartAccount}', [ChartAccountController::class, 'update']);
Route::delete('chart-accounts/{chartAccount}', [ChartAccountController::class, 'destroy']);

Route::get('revenue-types/{revenueType}', [RevenueTypeController::class, 'show']);
Route::post('revenue-types', [RevenueTypeController::class, 'store']);
Route::match(['put', 'patch'], 'revenue-types/{revenueType}', [RevenueTypeController::class, 'update']);
Route::delete('revenue-types/{revenueType}', [RevenueTypeController::class, 'destroy']);
