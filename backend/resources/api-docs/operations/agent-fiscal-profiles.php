<?php

return [
    'POST /agent-fiscal-profiles' => [
        'summary' => 'Create an agent fiscal profile',
        'description' => 'Creates the fiscal profile of an agent (referent_id) for a validity range valid_from..valid_until (open-ended when valid_until is null). withholding_rate and withholding_base_percent (0-100) are required when withholding_enabled is true and refused otherwise; enasarco_mandate_type (mono or pluri) is required when enasarco_enabled is true and refused otherwise. 422 when the range overlaps another profile of the same referent. Returns 201 with the profile and the actor\'s record and field permissions. Requires agent-fiscal-profiles.create.',
    ],
    'GET /agent-fiscal-profiles/{agentFiscalProfile}' => [
        'summary' => 'Get an agent fiscal profile',
        'description' => 'Returns the fiscal profile with its referent, VAT rate, payment method and cost type, plus the actor\'s record and field permissions; fields the actor may not see are omitted. Requires agent-fiscal-profiles.view.',
    ],
    'PUT /agent-fiscal-profiles/{agentFiscalProfile}' => [
        'summary' => 'Update an agent fiscal profile',
        'description' => 'Updates the fiscal profile. PUT takes the full body; PATCH is partial, omitted fields keep their saved value and the same cross-field rules (withholding and ENASARCO switches, non-overlapping validity range, 422) are applied to the resulting profile. Changing a field the actor may not edit is refused with 422. Returns the updated profile. Requires agent-fiscal-profiles.update.',
    ],
    'DELETE /agent-fiscal-profiles/{agentFiscalProfile}' => [
        'summary' => 'Delete an agent fiscal profile',
        'description' => 'Deletes the fiscal profile and answers 204. 409 when a commission settlement (draft, issued or cancelled) references it. Requires agent-fiscal-profiles.delete.',
    ],
];
