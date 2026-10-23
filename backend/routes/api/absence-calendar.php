<?php

use App\Http\Controllers\AbsenceCalendar\AbsenceCalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence calendar (spec 0279)
|--------------------------------------------------------------------------
|
| Authorization: absence-requests.calendarOwn|calendarAll (calendarAll shows
| everyone, calendarOwn shows self plus the org-chart team, spec 0309).
| Required from routes/api/hr.php INSIDE the `auth:sanctum` group.
*/

Route::get('absences/calendar', [AbsenceCalendarController::class, 'index']);
