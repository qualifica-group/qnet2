<?php

use App\Http\Controllers\Skills\SkillController;
use App\Http\Controllers\Skills\SkillForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Skills catalogue (spec 0286)
|--------------------------------------------------------------------------
|
| Authorization (skills.view/create/update/delete) is enforced server-side in
| the controller via SkillPolicy. Required from routes/api/hr.php INSIDE the
| `auth:sanctum` group. `for-select` is declared ABOVE the `{skill}` route so
| it is not swallowed by the binding; the list is the generic `skills` table
| domain.
*/

Route::get('skills/for-select', SkillForSelectController::class);
Route::get('skills/{skill}', [SkillController::class, 'show']);
Route::post('skills', [SkillController::class, 'store']);
Route::match(['put', 'patch'], 'skills/{skill}', [SkillController::class, 'update']);
Route::delete('skills/{skill}', [SkillController::class, 'destroy']);
