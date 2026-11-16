<?php

return [
    'GET /contracts/for-select' => [
        'summary' => 'List contracts for a select',
        'description' => 'Lightweight option list of the contracts visible to the caller (same record scope as the contracts list), most recently accepted first. A contract has no code of its own: label is its offer\'s code and title, subtitle the customer and acceptance date. search matches the offer code or title; offset and limit (max 100) paginate; ids adds specific contracts to show a form\'s current value. Response is items plus pagination. Needs only authentication.',
    ],
    'GET /contracts/multi-programmable-lines' => [
        'summary' => 'List the lines programmable into one multi-offer work order',
        'description' => 'For 2 to 50 contract_ids, returns a summary of each contract (offer code and title, customer) and the revenue lines of their offers with product branch, eligibility and the work order already occupying each line in the contract\'s current renewal period. The caller needs contracts.program on every contract (403 for the whole request otherwise); 422 on contract_ids naming the contracts that are not validated/closed-won or whose offer has no product allowing multi-offer work orders.',
    ],
    'POST /contracts/multi-quote-work-orders' => [
        'summary' => 'Create one work order from lines of several contracts',
        'description' => 'Creates a single multi-offer work order (no offer of its own) from at least two quote_line_ids taken from the offers of 2 to 50 contract_ids, all or nothing. Same fields as programming a single contract: type (processing or project), start_date, optional title, supervisor_ids, task_template_id and attribute_values. Lines must belong to those offers, be revenue lines not yet programmed, sit in the same product branch allowing multi-offer work orders and span at least two offers (422 otherwise). Same 403/422 gate as the line listing. Returns 201 with the work order and its permissions block.',
    ],
    'GET /contracts/{contract}' => [
        'summary' => 'Get a contract',
        'description' => 'Returns the contract with the data of its origin offer (customer, referent, company, sites, commercial, managers, offer lines and totals), its status, acceptance, validation, renewal and expiry dates, termination data, suspension, expiry/renewal alert, renewal history, plus the caller\'s permissions block. Requires contracts.view and the contract within the caller\'s visibility scope (403 otherwise).',
    ],
    'PUT /contracts/{contract}' => [
        'summary' => 'Update a contract',
        'description' => 'Partial update (PATCH accepted): contract_status_id (an active status), renewal_date (not after expiry_date, also compared with the stored value), expiry_date, payment_notes, comments, and on the origin offer its title (empty restores the automatic title) and offer_lines (full replacement of the revenue lines, at least one; totals are recalculated). A change to a field the caller may not edit is a 422. Requires contracts.update within the caller\'s scope. Returns the contract with its permissions block.',
    ],
    'POST /contracts/{contract}/change-status' => [
        'summary' => 'Change the status of a working contract',
        'description' => 'Moves a contract that is still working (status in the open or pending group) to another active status of the open or pending group, given as contract_status_id. It never validates, terminates or reopens a contract: 422 when the contract is closed. Activity-logged. Requires contracts.changeStatus within the caller\'s scope. Returns the contract.',
    ],
    'GET /contracts/{contract}/programmable-lines' => [
        'summary' => 'List the offer lines programmable into work orders',
        'description' => 'Returns the revenue lines of the contract\'s offer, each with its product and unit and the work order that already occupies it in the current renewal period (null when free). Requires contracts.program within the caller\'s scope, and the contract must be closed-won or validated and not suspended (403 otherwise).',
    ],
    'POST /contracts/{contract}/reactivate' => [
        'summary' => 'Reactivate a suspended or closed contract',
        'description' => 'A suspended contract (no body) returns to the status it had before suspension, allowed only while its offer is back in a won status. A closed contract (won or lost) requires contract_status_id of an active open or pending status; its termination and validation data are cleared, so it can be validated again, and its work orders stopped by the closure are restored. 422 for a contract that is neither suspended nor closed. Activity-logged. Requires contracts.reactivate within scope. Returns the contract.',
    ],
    'POST /contracts/{contract}/renewals' => [
        'summary' => 'Renew a contract for a new period',
        'description' => 'Opens a new renewal period: records the replaced dates in the renewal history and sets the new expiry_date (required, after the current expiry) and optional renewal_date (not after the expiry). The status does not change; offer lines become programmable again in the new period. Requires contracts.program within scope, on a closed-won or validated, non-suspended contract (403). Returns 201 with the contract.',
    ],
    'DELETE /contracts/{contract}/renewals/{renewal}' => [
        'summary' => 'Undo the latest renewal of a contract',
        'description' => 'Reverts the renewal: the contract gets back the renewal and expiry dates it had before and the history row is removed. 422 when it is not the latest renewal or when work orders are already programmed in its period; 404 when the renewal belongs to another contract. Same permission gate as renewing. Returns the contract.',
    ],
    'POST /contracts/{contract}/terminate' => [
        'summary' => 'Terminate a contract',
        'description' => 'Closes the contract negatively with terminated_at (not in the future) and termination_reason, both required; contract_status_id, if sent, must be an active closed-lost status (default: the system terminated status). Its work orders are closed: all of them for a cancelled status, only the open work otherwise, remembering their status for a later reactivation. 422 when already terminated. Activity-logged. Requires contracts.terminate within scope. Returns the contract.',
    ],
    'POST /contracts/{contract}/validate' => [
        'summary' => 'Validate a contract',
        'description' => 'Marks the contract as validated on validated_at (default today, never in the future) and moves it to contract_status_id, an active closed-won status (default: the system validated status). 422 when already validated, closed, suspended, or when the offer has no revenue line. Activity-logged. Requires contracts.validate within scope. Returns the contract.',
    ],
    'POST /contracts/{contract}/work-orders' => [
        'summary' => 'Create a work order from a contract',
        'description' => 'Programs one work order from a group of the contract\'s offer revenue lines (quote_line_ids, not yet programmed in the current period) with type (processing or project), start_date, optional title, supervisor_ids, task_template_id (its tasks are generated) and attribute_values. The work order gets a COM- code and its supervisors are notified. 422 for a line outside the offer or already programmed. Requires contracts.program within scope on a closed-won or validated, non-suspended contract (403). Returns 201 with the work order and its permissions block.',
    ],
    'POST /contracts/{contract}/work-orders/batch' => [
        'summary' => 'Create several work orders from a contract at once',
        'description' => 'Creates one work order per entry of groups (1 to 50), each with the same fields as the single programming call; a line may appear in only one group. All or nothing: any invalid group rolls back the whole batch with a 422 keyed groups.{index}. Same permission gate as the single call. Returns 201 with the list of created work orders.',
    ],
];
