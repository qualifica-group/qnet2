<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Labour consultant company codes (spec 0430, D-4)
    |--------------------------------------------------------------------------
    |
    | The consultant's files name a company by its own code (column AZIENDA).
    | Each code maps to the VAT number of the company in the `companies`
    | table, the same in every environment. A code missing here, or a VAT
    | number with no company, makes the import reject the row. Add a code
    | when the consultant starts reporting a new company.
    */
    'company_codes' => [
        '1' => '10447341214',   // Qualifica Group Training
        '2' => '10072561219',   // Qualifica Marketing
        '3' => '09075631219',   // Qualifica Group Formazione e Lavoro Impresa sociale
        '4' => '09537471212',   // Qualifica Group
        '7' => '10447371211',   // Qualifica Group Edujob
        '8' => '10447361212',   // Qualifica Group Form
        '9' => '10447351213',   // Qualifica Group Skills Job
        '10' => '10072571218',  // Qualifica Consulting
        '11' => '10447381210',  // Qualifica Group Academy
        '14' => '07770971211',  // Italian Quality Institute
        '15' => '04837460262',  // Qualifica Group Berit
    ],
];
