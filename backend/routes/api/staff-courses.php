<?php

use App\Http\Controllers\StaffCourses\StaffCourseController;
use App\Http\Controllers\StaffCourses\StaffCourseForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff courses catalogue (spec 0284)
|--------------------------------------------------------------------------
|
| Authorization (staff-courses.view/create/update/delete) is enforced
| server-side in the controller via StaffCoursePolicy. Required from
| routes/api/hr.php INSIDE the `auth:sanctum` group. `for-select` is declared
| ABOVE the `{staffCourse}` route so it is not swallowed by the binding; the
| list is the generic `staff-courses` table domain.
*/

Route::get('staff-courses/for-select', StaffCourseForSelectController::class);
Route::get('staff-courses/{staffCourse}', [StaffCourseController::class, 'show']);
Route::post('staff-courses', [StaffCourseController::class, 'store']);
Route::match(['put', 'patch'], 'staff-courses/{staffCourse}', [StaffCourseController::class, 'update']);
Route::delete('staff-courses/{staffCourse}', [StaffCourseController::class, 'destroy']);
