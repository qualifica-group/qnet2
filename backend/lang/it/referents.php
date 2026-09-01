<?php

return [
    // Spec 0090, D-10: il vincolo unique a DB su `user_id` è la rete di
    // sicurezza, questo è il messaggio che vede l'operatore, che nomina il
    // referente che occupa già il legame.
    'user_already_linked' => 'Questo utente è già collegato al referente ":name".',
];
