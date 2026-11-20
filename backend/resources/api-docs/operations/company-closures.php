<?php

return [
    'POST /company-closures' => [
        'summary' => 'Create a company closure',
        'description' => 'Creates a closure of at most 31 days. kind is holiday (a day off for everyone, no absence) or collective_leave, which requires absence_type_id and generates an approved full-day absence for every active employee concerned, skipping who already has an absence in the period or no working day (reported in generation: created and skipped with reason). applies_to_all_sites=false requires operational_site_ids. repeats_yearly only for a single-day holiday. Returns 201 with the closure. Requires company-closures.create.',
    ],
    'GET /company-closures/{companyClosure}' => [
        'summary' => 'Get a company closure',
        'description' => 'Returns the closure with its period, kind, absence type, sites (or all sites), yearly repetition, whether it has already started and how many absences it generated, plus the caller\'s permissions block. Requires company-closures.view.',
    ],
    'PUT /company-closures/{companyClosure}' => [
        'summary' => 'Update a company closure',
        'description' => 'Replaces the whole closure with the same payload and rules as creation (PATCH accepted). A collective leave regenerates its absences from scratch (generation in the response); turning it into a holiday removes them. 409 when the closure has already started, unless it repeats yearly. Requires company-closures.update.',
    ],
    'DELETE /company-closures/{companyClosure}' => [
        'summary' => 'Delete a company closure',
        'description' => 'Deletes the closure and the absences it generated; returns 204. 409 when the closure has already started, unless it repeats yearly. Requires company-closures.delete.',
    ],
];
