<?php

use App\Http\Controllers\AgentFiscalProfiles\AgentFiscalProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Agent commissions (spec 0212)
|--------------------------------------------------------------------------
|
| Fiscal profiles of the agents. Authorization
| (agent-fiscal-profiles.view/create/update/delete) is enforced server-side in
| AgentFiscalProfileController via AgentFiscalProfilePolicy on every endpoint.
| Required from routes/api/accounting.php, itself inside the `auth:sanctum`
| group of routes/api.php, so every route below inherits that middleware.
*/

Route::get('agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'show']);
Route::post('agent-fiscal-profiles', [AgentFiscalProfileController::class, 'store']);
Route::match(['put', 'patch'], 'agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'update']);
Route::delete('agent-fiscal-profiles/{agentFiscalProfile}', [AgentFiscalProfileController::class, 'destroy']);
