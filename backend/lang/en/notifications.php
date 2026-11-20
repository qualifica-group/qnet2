<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Notification detail card
|--------------------------------------------------------------------------
|
| Labels of the "scheda dettagli" every notification email carries about the
| record it talks about (direttiva utente 2026-08-04).
|
| A DEDICATED file and not `lang/it.json` on purpose: these are generic
| single words ("Name", "Source", "Status"). As JSON keys they would be the
| translation of that word EVERYWHERE `__()` is called with it, in any
| future feature. Namespaced here, they translate this table and nothing
| else.
|
*/

return [

    'fields' => [
        // Anagrafica (Registry)
        'name' => 'Name',
        'type' => 'Type',
        'account_managers' => 'Account managers',

        // Opportunita' / Gestione richieste (Opportunity)
        'title' => 'Title',
        'client' => 'Client',
        'operational_site' => 'Operational site',
        'status' => 'Status',
        'operator' => 'Operator',

        // Shared
        'source' => 'Source',
        'supervisor' => 'Supervisor',

        // Trasferimento contatto
        'contact' => 'Contact',
        'origin_site' => 'Origin site',
        'destination_site' => 'Destination site',
        'previous_operator' => 'Previous operator',
        'new_operator' => 'New operator',
        'performed_by' => 'Performed by',
        'date' => 'Date',

        // Task (spec 0119)
        'priority' => 'Priority',
        'end_date' => 'Due date',
        'requester' => 'Requester',
        'assignees' => 'Assignees',

        // Commessa (spec 0351)
        'start_date' => 'Start date',
        'supervisors' => 'Supervisors',
        'participants' => 'Participants',

        // Registry tax data (spec 0381)
        'vat_number' => 'VAT number',
        'tax_code' => 'Tax code',
        'sdi_code' => 'SDI code',

        // Absence (spec 0381)
        'person' => 'Person',
        'period' => 'Period',
        'reason' => 'Reason',

        // Change request (spec 0381)
        'field' => 'Field',
        'current_value' => 'Current value',
        'requested_value' => 'Requested value',
        'outcome' => 'Outcome',
        'note' => 'Note',
        'handled_by' => 'Handled by',

        // Deliverable work order line (spec 0381)
        'product' => 'Product',
        'payment_status' => 'Payment status',

        // Referent, proforma request, purchase request (spec 0381)
        'referent_type' => 'Referent type',
        'registries' => 'Registries',
        'work_order' => 'Work order',
        'supplier' => 'Supplier',
        'assignee' => 'Assigned to',
        'subject' => 'Subject',
        'requested_on' => 'Requested on',
        'total' => 'Total',
    ],

    'values' => [
        'client' => 'Client',
        'supplier' => 'Supplier',
        'empty' => '—',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
        'proforma_consultancy' => 'Consultancy',
        'proforma_institution' => 'Institution',
        'proforma_pending' => 'Pending',
        'proforma_issued' => 'Issued',
        'yes' => 'Yes',
        'no' => 'No',
    ],

    /*
    | Label of the button that opens the module (spec 0381, D-2): the key is
    | the first segment of the destination path.
    */
    'actions' => [
        'registries' => 'Open registry',
        'referents' => 'Open referent',
        'opportunities' => 'Open opportunity',
        'quotes' => 'Open quote',
        'request-management' => 'Open request',
        'enrollee-management' => 'Open enrollee',
        'work-orders' => 'Open work order',
        'tasks' => 'Open task',
        'purchase-requests' => 'Open purchase request',
        'proforma-requests' => 'Open proforma request',
        'absences' => 'Open absences',
        'field-change-requests' => 'Open change request',
        'default' => 'Open in QNet',
    ],

];
