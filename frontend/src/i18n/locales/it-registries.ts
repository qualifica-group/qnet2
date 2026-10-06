/**
 * Dominio Anagrafiche (spec 0020). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); la forma pubblica non cambia.
 */

export const registries = {
  title: 'Anagrafiche',
  subtitle: 'Sfoglia, filtra e gestisci le anagrafiche clienti/fornitori della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le anagrafiche.',
  columns: {
    name: 'Nome',
    source: 'Fonte',
    is_supplier: 'Fornitore',
    agreement_status: 'Stato convenzione',
    size_class: 'Classe dimensionale',
    primary_contact: 'Contatto principale',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio anagrafica',
    subtitle: "Visualizzazione in sola lettura dell'anagrafica selezionata.",
    loadError: "Impossibile caricare l'anagrafica. Riprova.",
    details: 'Dettagli',
    // Scheda in sola lettura dei documenti dell'anagrafica nei dettagli di
    // Opportunita', Offerte e Commesse (spec 0173).
    registryDocumentsTab: 'Documenti anagrafica',
    // Tab dei record collegati al cliente sotto la scheda (spec 0199).
    related: {
      title: 'Record collegati',
      countLabel_one: '{{count}} record collegato',
      countLabel_other: '{{count}} record collegati',
    },
    // Etichette della striscia KPI: sono SUE, non quelle del form, perche' le
    // sezioni sotto portano gia' "Referenti"/"Settori" sulle righe che ne
    // elencano i nomi.
    stats: {
      referents: 'Referenti collegati',
      managers: 'Gestori assegnati',
      sectors: 'Settori merceologici',
      employees: 'Dipendenti',
    },
  },
  form: {
    newRegistry: 'Nuova anagrafica',
    createTitle: 'Crea anagrafica',
    createSubtitle: 'Aggiungi una nuova anagrafica alla tua organizzazione.',
    source: 'Fonte',
    sourcePlaceholder: 'Seleziona una fonte…',
    sourceSearch: 'Cerca fonti…',
    sourceEmpty: 'Nessuna fonte trovata.',
    sourceError: 'Impossibile caricare le fonti.',
    sectors: 'Settori',
    sectorsPlaceholder: 'Seleziona settori…',
    sectorsSearch: 'Cerca settori…',
    sectorsEmpty: 'Nessun settore trovato.',
    sectorsError: 'Impossibile caricare i settori.',
    sectorsRemove: 'Rimuovi settore',
    referents: 'Referenti',
    referentsPlaceholder: 'Seleziona referenti…',
    referentsSearch: 'Cerca referenti…',
    referentsEmpty: 'Nessun referente trovato.',
    referentsError: 'Impossibile caricare i referenti.',
    referentsRemove: 'Rimuovi referente',
    managers: 'Gestori account',
    managersPlaceholder: 'Seleziona gestori account…',
    managersSearch: 'Cerca gestori account…',
    managersEmpty: 'Nessun gestore account trovato.',
    managersError: 'Impossibile caricare i gestori account.',
    managersRemove: 'Rimuovi gestore account',
    managersMax: 'Puoi selezionare al massimo {{max}} gestori account.',
    managersHint: 'I gestori account sono in ordine di importanza (dal primo in alto): l’ordine viene ereditato dagli altri moduli. Assegna una persona a ogni riga, svuotala per lasciarla libera o riordinala con le frecce.',
    managersAddSlot: 'Aggiungi gestore account',
    managerSlotLabel: 'Gestore account {{n}}',
    managerSlotEmpty: 'Slot vuoto',
    managerMoveUp: 'Sposta su',
    managerMoveDown: 'Sposta giù',
    managerRemoveSlot: 'Rimuovi slot',
    supervisor: 'Supervisore',
    supervisorPlaceholder: 'Seleziona un supervisore…',
    commercial: 'Commerciale',
    commercialPlaceholder: 'Seleziona un referente commerciale…',
    reporter: 'Segnalatore',
    reporterPlaceholder: 'Seleziona un segnalatore…',
    vatGroup: 'Gruppo IVA',
    vatGroupMax: 'Il gruppo IVA può contenere al massimo 191 caratteri.',
    isSupplier: 'Fornitore',
    isQualifiedSupplier: 'Fornitore qualificato',
    agreementStatus: 'Stato convenzione',
    agreementStatusPlaceholder: 'Seleziona uno stato…',
    agreementNotes: 'Note convenzione',
    agreementNotesMax: 'Le note convenzione possono contenere al massimo 5000 caratteri.',
    sizeClass: 'Classe dimensionale',
    sizeClassPlaceholder: 'Seleziona una classe…',
    employeeCount: 'Numero dipendenti',
    employeeCountInvalid: 'Inserisci un numero intero non negativo.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Anagrafica creata con successo.',
    updated: 'Anagrafica aggiornata con successo.',
    deleted: 'Anagrafica eliminata con successo.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: "Impossibile eliminare l'anagrafica. Riprova.",
    deleteForbidden: 'Non puoi eliminare questa anagrafica.',
    leaveConfirm: {
      title: 'Uscire senza salvare?',
      description: "L'anagrafica non è ancora stata creata: i dati inseriti andranno persi.",
      confirm: 'Esci senza salvare',
      cancel: 'Continua a compilare',
    },
    sections: {
      identity: {
        title: 'Dati anagrafici',
      },
      relations: {
        title: 'Relazioni',
      },
      team: {
        title: 'Team',
      },
      business: {
        title: 'Dati commerciali',
      },
      contacts: {
        title: 'Contatti',
      },
      addresses: {
        title: 'Indirizzi',
      },
    },
  },
}
