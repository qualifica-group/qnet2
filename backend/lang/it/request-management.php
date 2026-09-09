<?php

return [
    // Direttiva utente 2026-09-09: la chiusura con esito positivo esige
    // l'identificativo fiscale del cliente, indifferentemente quale dei due.
    'fiscal_identity_required_for_status' => 'Non puoi chiudere la richiesta con esito positivo senza il codice fiscale o la partita IVA del cliente.',
    // Stessa regola, ma sul RECORD invece che sulla transizione (decisione
    // utente 2026-09-09): il pannello non salva una richiesta che STA in
    // chiuso-positivo finche' il dato manca, anche se questo salvataggio
    // cambia altro.
    'fiscal_identity_required_on_closed_won' => 'La richiesta e\' chiusa con esito positivo: inserisci il codice fiscale o la partita IVA del cliente per poterla salvare.',
];
