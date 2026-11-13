<?php

return [
    'POST /enasarco-parameters' => [
        'summary' => 'Create the ENASARCO parameters of a year',
        'description' => 'Stores the ENASARCO contribution rates and ceilings of one year (unique, 2000-2100): total_rate and agent_share_percent are percentages (0-100, two decimals), single_firm_ceiling and multi_firm_ceiling the yearly ceilings for single-firm and multi-firm agents. Commission settlements use the parameters of the year of their period end. Returns the record with a permissions block. Requires enasarco-parameters.create.',
    ],
    'GET /enasarco-parameters/{enasarcoParameter}' => [
        'summary' => 'Get the ENASARCO parameters of a year',
        'description' => 'Returns year, total rate, agent share, single-firm and multi-firm ceilings and notes, plus a permissions block for the caller; fields the caller may not see are omitted. Requires enasarco-parameters.view.',
    ],
    'PUT /enasarco-parameters/{enasarcoParameter}' => [
        'summary' => 'Update the ENASARCO parameters of a year',
        'description' => 'With PUT every field is required (full replace); with PATCH omitted fields keep their stored value. The year must stay unique. Returns the record with a permissions block. Requires enasarco-parameters.update.',
    ],
    'DELETE /enasarco-parameters/{enasarcoParameter}' => [
        'summary' => 'Delete the ENASARCO parameters of a year',
        'description' => 'Deletes the year\'s parameters and answers 204 with no content. 409 when a commission settlement with ENASARCO enabled, already issued, document received or approved, has its period ending in that year. Requires enasarco-parameters.delete.',
    ],
];
