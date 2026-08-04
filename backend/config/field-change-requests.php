<?php

/*
|--------------------------------------------------------------------------
| Protected fields registry
|--------------------------------------------------------------------------
|
| Spec 0078, decision D-1. The generic "field change request" system reads
| this file (via App\FieldChangeRequests\ProtectedFieldRegistry) to know
| which fields, on which resources, an actor must PROPOSE a change to rather
| than write directly. Protecting another field on another module is a new
| entry here — zero new code.
|
*/

return [

    'resources' => [

        'request-management' => [
            // Deep-link of the record, used to build the notification's
            // action_url (F-8).
            'record_path' => '/request-management',

            // i18n key of the module, shown as the "domain/module" a request
            // belongs to.
            'label' => 'navigation.requestManagement',

            'fields' => [

                'source_id' => [
                    // Generated permission: "{resource}.{ability}" ==
                    // request-management.updateSource. Only an actor holding
                    // it may write this field directly on any of the three
                    // write channels (F-4).
                    'ability' => 'updateSource',

                    // The TableDefinition column id, used to apply an
                    // approved value via TableDefinition::updateCell() (D-7).
                    'column' => 'source',

                    // i18n key of the field, shown as "the field being
                    // changed".
                    'label' => 'requestManagement.columns.source',
                ],

            ],
        ],

    ],

];
