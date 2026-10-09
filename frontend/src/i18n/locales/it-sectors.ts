/**
 * Stringhe del dominio Settori (spec 0018), in un file separato per i
 * limiti di dimensione (vedi `.claude/rules/engineering.md` §6). Rispecchia
 * la forma di `productCategories`/`referentTypes`.
 */
export const sectors = {
  title: 'Settori',
  subtitle: 'Sfoglia, filtra e gestisci i settori.',
  forbidden: 'Non hai i permessi per visualizzare i settori.',
  columns: {
    code: 'Codice',
    name: 'Nome',
    parent: 'Padre',
    is_active: 'Attivo',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli settore',
    subtitle: 'Vista di sola lettura del settore selezionato.',
    loadError: 'Impossibile caricare il settore. Riprova.',
  },
  badges: {
    notActive: 'Non attivo',
  },
  form: {
    newSector: 'Nuovo settore',
    createTitle: 'Crea settore',
    createSubtitle: 'Aggiungi un nuovo settore.',
    editTitle: 'Modifica settore',
    editSubtitle: 'Aggiorna il settore selezionato.',
    code: 'Codice',
    codePlaceholder: 'Es. 01, 07a, NA',
    codeMax: 'Il codice deve avere al massimo 20 caratteri.',
    name: 'Nome',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome deve avere al massimo 191 caratteri.',
    parent: 'Settore padre',
    parentPlaceholder: 'Seleziona un settore padre…',
    parentSearch: 'Cerca settori…',
    parentEmpty: 'Nessun settore trovato.',
    parentNoMatch: 'Nessun risultato.',
    parentError: 'Impossibile caricare i settori.',
    noParent: 'Nessun padre (settore radice)',
    isActive: 'Attivo',
    isActiveHint:
      'Un settore non attivo, con tutti i suoi sotto-settori, non compare più nei campi di scelta. I collegamenti esistenti restano validi.',
    isActiveInheritedNotice: 'Non attivo perché lo è «{{sector}}»',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Settore creato con successo.',
    updated: 'Settore aggiornato con successo.',
    deleted: 'Settore eliminato con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare il settore. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo settore.',
    deleteInUse: 'Questo settore ha sotto-settori e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Codice, nome, padre e stato del settore.',
      },
    },
  },
}
