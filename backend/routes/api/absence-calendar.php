<?php

use App\Http\Controllers\AbsenceCalendar\AbsenceCalendarController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Absence calendar (spec 0279)
|--------------------------------------------------------------------------
|
| Authorization: absence-requests.calendarOwn|calendarTeam|calendarAll (the
| widest held decides whose absences are shown).
| Required from routes/api/hr.php INSIDE the `auth:sanctum` group.
*/

Route::get('absences/calendar', [AbsenceCalendarController::class, 'index']);
