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

    /*
    |--------------------------------------------------------------------------
    | Payslips (spec 0431, D-1, D-6)
    |--------------------------------------------------------------------------
    |
    | Payment method code and cost type code of the payslip documents created
    | by the net file import.
    */
    'payslip' => [
        'payment_method_code' => env('PAYSLIP_PAYMENT_METHOD_CODE', 'bonifico_bancario'),
        'cost_type_code' => env('PAYSLIP_COST_TYPE_CODE', '63.01.001'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Personnel cash flow forecast (spec 0432, D-2..D-4)
    |--------------------------------------------------------------------------
    |
    | The regular payslip type that carries the monthly net, the default payment
    | days of the net salary and of the F24 (both in the month after the
    | competence month) and the extra monthly payments (thirteenth, fourteenth)
    | as month and day of payment.
    */
    'forecast' => [
        'regular_payslip_type' => env('PAYROLL_FORECAST_REGULAR_PAYSLIP_TYPE', 'MENSILITA_NORMALE'),
        'salary_day' => (int) env('PAYROLL_FORECAST_SALARY_DAY', 15),
        'f24_day' => (int) env('PAYROLL_FORECAST_F24_DAY', 16),
        'extra_payments' => [
            'thirteenth' => ['month' => 12, 'day' => 20],
            'fourteenth' => ['month' => 7, 'day' => 15],
        ],
    ],
];
