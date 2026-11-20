<?php

return [
    'POST /quote-system-statuses' => [
        'summary' => 'Create a quote system status',
        'description' => 'Creates a quote system status with a unique name, a color and a behavior (open, pending, validated, closed_won, closed_lost); the behavior decides what the linked working statuses do (contract on closed_won, Gestione Iscritti on validated and closed_won). It is placed after the existing custom statuses and before Vinto and Perso. Returns 201 with the status and the user\'s permissions on it. Requires quote-system-statuses.create.',
    ],
    'GET /quote-system-statuses/for-select' => [
        'summary' => 'Search quote system statuses for a selector',
        'description' => 'Returns a paginated list of quote system statuses in display order, each as id, label (name) and meta with color, behavior and system_key (null for custom statuses). Supports search on the name, offset and limit, and ids[] to append already selected statuses. Any authenticated user.',
    ],
    'POST /quote-system-statuses/reorder' => [
        'summary' => 'Reorder the custom quote system statuses',
        'description' => 'Sets the display order of the custom quote system statuses from ordered_ids, which must list every custom status exactly once and no system status (422 otherwise); Aperto stays first, Vinto and Perso last. Returns every status with its new sort_order and system_key. Requires quote-system-statuses.update.',
    ],
    'GET /quote-system-statuses/{quoteSystemStatus}' => [
        'summary' => 'Get a quote system status',
        'description' => 'Returns the quote system status, with the number of working statuses linked to it, and the user\'s permissions on it. Requires quote-system-statuses.view.',
    ],
    'PUT /quote-system-statuses/{quoteSystemStatus}' => [
        'summary' => 'Update a quote system status',
        'description' => 'Updates the submitted name, color or behavior. The system statuses Aperto, Vinto and Perso keep their behavior (422 otherwise). A new behavior is copied at once onto the group of every linked working status, without creating or suspending contracts of the offers already there. Returns the status with the user\'s permissions. Requires quote-system-statuses.update.',
    ],
    'DELETE /quote-system-statuses/{quoteSystemStatus}' => [
        'summary' => 'Delete a quote system status',
        'description' => 'Deletes a custom quote system status and answers 204 with no content. 422 for a system status; 409 when working statuses still link it, naming them. Requires quote-system-statuses.delete.',
    ],
];
