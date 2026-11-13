<?php

return [
    'DELETE /bank-reconciliations/{bankReconciliation}' => [
        'summary' => 'Delete a bank reconciliation',
        'description' => 'Deletes a saved bank reconciliation of a financial account. The adjustment movement it may have created is not deleted: it stays as a normal, already reconciled treasury movement. Answers with data null. Requires treasury-movements.reconcile.',
    ],
];
