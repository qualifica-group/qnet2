<?php

return [
    'POST /referents' => [
        'summary' => 'Create a referent',
        'description' => 'Creates a referent (a contact person, also used for agents) with contact_scope internal (company staff) or external (third party), optional referent_type_id, notes, user_id (a user not already linked to another referent) and supplier_registry_id (a supplier registry the agent is paid through). personal_data is required: the anagraphic card with contacts and addresses, from which the referent name is derived; it must include at least one phone number not already used by a user, registry or other referent. Returns 201 with the referent and the record and field permissions. Requires referents.create.',
    ],
    'GET /referents/for-select' => [
        'summary' => 'List referents for a select',
        'description' => 'Returns referents as select options (id, name as label, primary contacts as meta) ordered by name. registry_id restricts to the referents linked to that registry; search matches the name; offset, limit, ids[] (hydration) and include_total=0 (has_more instead of the count) as usual. Responds with items and pagination. Needs only authentication.',
    ],
    'GET /referents/{referent}' => [
        'summary' => 'Get a referent',
        'description' => 'Returns the referent with its type, linked user, supplier registry, contact scope, notes and the full anagraphic card (contacts and addresses), plus the record and field permissions; fields the actor may not see are omitted. Requires referents.view.',
    ],
    'PUT /referents/{referent}' => [
        'summary' => 'Update a referent',
        'description' => 'Updates the submitted fields of the referent (partial update, PATCH accepted); referent_type_id null removes the type, personal_data when sent updates the card and the derived name, when omitted leaves it untouched. 422 when user_id is linked to another referent, when the supplier registry is not a supplier, or when the supplier link is moved while the agent has approved commission settlements. Returns the referent. Requires referents.update.',
    ],
    'DELETE /referents/{referent}' => [
        'summary' => 'Delete a referent',
        'description' => 'Deletes the referent with its anagraphic card and answers 204; registries that had it as primary referent get a new one elected. 409 when it is the referent, commercial or reporter of an opportunity. Requires referents.delete.',
    ],
    'GET /referents/{referent}/rewards' => [
        'summary' => 'List the rewards of a referent',
        'description' => 'Returns, under items, the rewards assigned to the referent, most recent first: type, status, notes, the origin opportunity or offer with its live context (registry, product categories, status, operator) and the related record. Requires rewarded-referents.view.',
    ],
];
