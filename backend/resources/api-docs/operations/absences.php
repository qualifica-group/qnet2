<?php

return [
    'GET /absences/calendar' => [
        'summary' => 'Get the absence calendar of a period',
        'description' => 'Returns, for the period from..to (Y-m-d, at most 62 days, 422 otherwise), every day with its non-working and holiday flags and, for each visible person, their closed days and one entry per working day covered by an approved or submitted absence request (type, colour, full day or part, minutes, whether the actor can open the request). business_function_id narrows the people to one business function. absence-requests.calendarAll shows every active person; absence-requests.calendarOwn shows the actor plus their org-chart subordinates; without either the call is refused with 403.',
    ],
    'GET /absences/summary' => [
        'summary' => 'Get the absence summary of the current user',
        'description' => 'Returns the counters and short lists behind the absences menu badge and dashboard block: the number of requests waiting for the actor\'s approval, the actor\'s own submitted requests, the actor\'s daily working minutes, their next five approved or submitted absences, and who is absent today (approved requests on a working day, at most 50) among the people the actor\'s calendar permission reveals. Requires absence-requests.viewAny.',
    ],
];
