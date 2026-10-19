/**
 * Azioni di record del dettaglio utente (barra azioni: attiva/disattiva,
 * impersona, elimina). Separate da `it.ts` per restare nei limiti di dimensione
 * (`.claude/rules/engineering.md` §6); confluiscono in `users.detail.actions`.
 */

export const usersDetailActions = {
  activate: 'Attiva',
  activated: 'Utente attivato.',
  stillInactive: "L'utente ha una data di cessazione già trascorsa e resta non attivo.",
  deactivate: 'Disattiva',
  deactivated: 'Utente disattivato.',
  deactivateConfirm: {
    title: 'Disattivare {{name}}?',
    description: "L'utente non potrà più accedere finché non verrà riattivato. I suoi dati restano invariati.",
  },
  toggleError: "Impossibile aggiornare lo stato dell'utente. Riprova.",
  impersonateConfirm: {
    title: 'Operare come {{name}}?',
    description:
      "Userai l'applicazione con i permessi di questo utente finché non torni al tuo account dal banner in alto.",
  },
  deleteConfirm: {
    title: 'Eliminare {{name}}?',
    description: "L'utente verrà eliminato definitivamente. L'operazione non può essere annullata.",
  },
  deleteInUse: "L'utente è operatore di lead o supervisore di opportunità: riassegnali prima di eliminarlo.",
}
