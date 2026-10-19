<?php

use App\Http\Controllers\UserSkills\UserSkillController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| User skills register (spec 0286)
|--------------------------------------------------------------------------
|
| Authorization: user-skills.viewAny|viewTeam|viewAll|manage
| (UserSkillPolicy, UserSkillVisibility). Required from routes/api/hr.php
| INSIDE the `auth:sanctum` group. `coverage` is declared ABOVE the
| `{userSkill}` route so it is not swallowed by the binding; the list is the
| generic `user-skills` table domain.
*/

Route::get('user-skills/coverage', [UserSkillController::class, 'coverage']);
Route::get('user-skills/{userSkill}', [UserSkillController::class, 'show']);
Route::post('user-skills', [UserSkillController::class, 'store']);
Route::match(['put', 'patch'], 'user-skills/{userSkill}', [UserSkillController::class, 'update']);
Route::delete('user-skills/{userSkill}', [UserSkillController::class, 'destroy']);
