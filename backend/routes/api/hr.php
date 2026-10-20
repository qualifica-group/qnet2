<?php

/*
|--------------------------------------------------------------------------
| HR section routes
|--------------------------------------------------------------------------
|
| HR org structure: business functions (spec 0010), job roles and
| job activities (spec 0271), org chart (spec 0273). Grouped here to keep routes/api.php under the file-size limit
| (engineering.md §6); required INSIDE the auth:sanctum group of
| routes/api.php, so every route still inherits that context.
|
*/

require __DIR__.'/business-functions.php';
require __DIR__.'/job-roles.php';
require __DIR__.'/job-activities.php';

require __DIR__.'/collective-agreements.php';
require __DIR__.'/employment-contracts.php';
require __DIR__.'/org-chart.php';
require __DIR__.'/absence-types.php';
require __DIR__.'/absence-requests.php';
require __DIR__.'/absence-balances.php';
require __DIR__.'/absence-calendar.php';
require __DIR__.'/staff-courses.php';
require __DIR__.'/staff-trainings.php';
require __DIR__.'/skills.php';
require __DIR__.'/user-skills.php';
