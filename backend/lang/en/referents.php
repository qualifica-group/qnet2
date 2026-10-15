<?php

return [
    // Spec 0090, D-10: the DB unique constraint on `user_id` is the safety
    // net, this is the message the operator actually sees, naming the
    // referent that already holds the link.
    'user_already_linked' => 'This user is already linked to referent ":name".',
    // Spec 0267, D-7: the supplier link is frozen once a settlement is approved.
    'supplier_link_locked' => 'The supplier registry cannot be changed: this agent has an approved settlement.',
];
