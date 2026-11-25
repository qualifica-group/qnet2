<?php

return [
    'POST /payroll-costs/import' => [
        'summary' => 'Preview or import the monthly payroll cost file',
        'description' => 'Multipart upload of the labour consultant\'s cost file (csv, txt or xlsx; the csv separator is tab, semicolon or comma and a non UTF-8 file is read as Windows-1252). Required columns: AZIENDA, Codice fiscale dipendente, Mese da, Anno da, Costo retribuzione, Costo contributivo, Costo inail, Ore ordinarie, TOTALE COSTO MENSILE; optional: Data assunzione, Data cessazione. A file holds one month: with dry_run=true (or 1) nothing is written and the response is the preview; with dry_run=false the month is replaced all-or-nothing, only if no row has errors. People are matched by the tax code of the user personal-data card and companies by the consultant company code (config payroll.company_codes). The response holds applied, period (YYYY-MM), summary (rows, people, companies, total_cost, replaced_rows) and errors (row, column, message). 422 on file for a missing column, an empty file or more than 2000 rows. Requires payroll-costs.view and payroll-costs.import.',
    ],
];
