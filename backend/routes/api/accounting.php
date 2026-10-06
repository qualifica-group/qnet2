<?php

/*
| Contabilita' (accounting): financial accounts (spec 0189), proforma requests
| (spec 0193) and active invoicing (spec 0194). Grouped here to keep
| routes/api.php within the file-size limit (engineering.md §6).
*/

require __DIR__.'/financial-accounts.php';
require __DIR__.'/proforma-requests.php';
require __DIR__.'/invoices.php';
require __DIR__.'/invoice-emails.php';
