<?php

use App\Http\Controllers\Rewards\RewardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reward inline status edit (spec 0060 §4)
|--------------------------------------------------------------------------
|
| The card's inline status edit (D-1): the only mutable surface on `rewards`
| outside the Opportunity/Gestione Richiesta chip payload — spec 0059's
| RewardAssignmentWriter remains the sole writer of every other field.
| Gated on `rewarded-referents.update` directly in RewardController (D-8: no
| dedicated Reward model policy). Extracted into its own file (file-size
| split, engineering.md §6), required from routes/api.php INSIDE the existing
| `auth:sanctum` group so this route inherits that same middleware/prefix
| context — same precedent as routes/api/referents.php.
*/
Route::patch('rewards/{reward}', [RewardController::class, 'updateStatus']);
