/**
 * I cinque configuratori del modulo Task (spec 0101, D-4): Stati, Tipologie,
 * Categorie, Priorità e Importanza. File satellite unico per mantenere `it.ts`
 * entro i limiti dimensionali (`.claude/rules/engineering.md` §6).
 *
 * I quattro lookup non-stato hanno forma IDENTICA (D-4) — cambia solo il
 * sostantivo — quindi sono costruiti da `lookupBundle()` invece di essere
 * copiati quattro volte. Sono tutti e quattro FEMMINILI (tipologia, categoria,
 * priorità, importanza), quindi le concordanze non cambiano mai fra loro.
 * `taskStatuses` è scritto per esteso per due motivi: porta
 * `completion_percentage` e il messaggio sulle righe di sistema (D-5), ed è
 * MASCHILE ("stato creato", non "creata").
 *
 * `columns.*` rispecchia ogni `ColumnCatalog`: dichiara `created_at` ma NON
 * `updated_at`, mentre `detail.updated_at` esiste perché il dettaglio legge il
 * `data` del CRUD, che porta entrambi i timestamp. Non "correggere"
 * l'asimmetria: riflette il backend.
 */

/**
 * Sostantivi per entità; tutto il resto del bundle è condiviso.
 *
 * La chiave `newTask*` NON viene costruita qui: una proprietà calcolata
 * `[newKey]` degraderebbe il tipo del bundle a index signature e
 * `TranslationResources` accetterebbe allora QUALSIASI chiave sul lato
 * italiano, annullando in silenzio la parità a compile-time su cui questo file
 * si appoggia. Ogni modulo la aggiunge sotto come letterale.
 */
interface LookupCopy {
  /** Singolare minuscolo usato a metà frase, e.g. "tipologia task". */
  singular: string
  /** Singolare in forma di titolo, e.g. "Tipologia Task". */
  Singular: string
  /** Plurale in forma di titolo, e.g. "Tipologie Task". */
  plural: string
}

function lookupBundle({ singular, Singular, plural }: LookupCopy) {
  return {
    forbidden: `Non hai i permessi per visualizzare le ${plural.toLowerCase()}.`,
    columns: {
      name: 'Nome',
      description: 'Descrizione',
      color: 'Colore',
      icon: 'Icona',
      sort_order: 'Ordine',
      is_active: 'Attiva',
      created_at: 'Creato il',
    },
    detail: {
      title: `Dettaglio ${singular}`,
      subtitle: 'Visualizzazione in sola lettura del record selezionato.',
      loadError: `Impossibile caricare la ${singular}. Riprova.`,
      description: 'Descrizione',
      color: 'Colore',
      icon: 'Icona',
      sort_order: 'Ordine',
      isActive: 'Attiva',
      created_at: 'Creato il',
      updated_at: 'Aggiornato il',
    },
    form: {
      createTitle: `Nuova ${singular}`,
      createSubtitle: `Crea una nuova ${singular}.`,
      editTitle: `Modifica ${singular}`,
      editSubtitle: `Aggiorna la ${singular} selezionata.`,
      name: 'Nome',
      description: 'Descrizione',
      color: 'Colore',
      icon: 'Icona',
      isActive: 'Attiva',
      save: 'Salva',
      saving: 'Salvataggio…',
      cancel: 'Annulla',
      created: `${Singular} creata correttamente.`,
      updated: `${Singular} aggiornata correttamente.`,
      deleted: `${Singular} eliminata correttamente.`,
      nameRequired: "Il nome è obbligatorio.",
      nameMax: 'Il nome può contenere al massimo 191 caratteri.',
      descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
      colorRequired: 'Il colore è obbligatorio.',
      colorInvalid: 'Scegli un colore dalla palette.',
      iconInvalid: "Scegli un'icona dal catalogo.",
      genericError: 'Qualcosa è andato storto. Riprova.',
      deleteError: `Impossibile eliminare la ${singular}. Riprova.`,
      deleteForbidden: `Non puoi eliminare questa ${singular}.`,
      // Solo FALLBACK: sul 409 il modulo mostra verbatim il messaggio del
      // backend, che nomina la risorsa. Questa si vede solo se il body non ne
      // porta uno.
      deleteInUse: `Questa ${singular} è usata da un task e non può essere eliminata.`,
      sections: {
        identity: {
          title: 'Dettagli',
          description: 'Nome, descrizione, colore, icona e stato.',
        },
      },
    },
    reorder: {
      openButton: 'Riordina',
      title: `Riordina ${plural}`,
      // Nessun riferimento a righe fissate in testa o in coda: a differenza
      // degli stati task, questi lookup non hanno righe di sistema, quindi
      // ogni riga è liberamente riordinabile.
      subtitle:
        "Trascina le righe per cambiarne l'ordine. L'ordine si riflette su tabella e menu a tendina.",
      dragHandleLabel: 'Trascina per riordinare',
      loadError: "Impossibile caricare l'elenco. Riprova.",
      saved: 'Ordine aggiornato correttamente.',
      forbidden: 'Non puoi riordinare queste righe.',
      genericError: "Impossibile aggiornare l'ordine. Riprova.",
      // La sheet di riordino elenca ANCHE le righe che un admin ha
      // disattivato — deve farlo, perche' l'endpoint valida `ordered_ids`
      // sull'insieme COMPLETO e ometterle darebbe 422 a ogni trascinamento.
      // Questo badge e' l'unica cosa che spiega all'admin perche' una riga
      // sparita da tutti i menu a tendina compaia qui.
      inactiveBadge: 'Disattivata',
    },
  }
}

const types = lookupBundle({
  singular: 'tipologia task',
  Singular: 'Tipologia Task',
  plural: 'Tipologie Task',
})
export const taskTypes = {
  ...types,
  form: { ...types.form, newTaskType: 'Nuova tipologia' },
}

const categories = lookupBundle({
  singular: 'categoria task',
  Singular: 'Categoria Task',
  plural: 'Categorie Task',
})
export const taskCategories = {
  ...categories,
  form: { ...categories.form, newTaskCategory: 'Nuova categoria' },
}

const priorities = lookupBundle({
  singular: 'priorità task',
  Singular: 'Priorità Task',
  plural: 'Priorità Task',
})
export const taskPriorities = {
  ...priorities,
  form: { ...priorities.form, newTaskPriority: 'Nuova priorità' },
}

const importances = lookupBundle({
  singular: 'importanza task',
  Singular: 'Importanza Task',
  plural: 'Importanze Task',
})
export const taskImportances = {
  ...importances,
  form: { ...importances.form, newTaskImportance: 'Nuova importanza' },
}

export const taskStatuses = {
  forbidden: 'Non hai i permessi per visualizzare gli stati task.',
  columns: {
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    icon: 'Icona',
    sort_order: 'Ordine',
    group: 'Fase',
    is_active: 'Attivo',
    completion_percentage: 'Completamento',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato task',
    subtitle: 'Visualizzazione in sola lettura del record selezionato.',
    loadError: 'Impossibile caricare lo stato task. Riprova.',
    description: 'Descrizione',
    color: 'Colore',
    icon: 'Icona',
    sort_order: 'Ordine',
    group: 'Fase',
    isActive: 'Attivo',
    completionPercentage: 'Percentuale di completamento',
    created_at: 'Creato il',
    updated_at: 'Aggiornato il',
  },
  form: {
    createTitle: 'Nuovo stato task',
    createSubtitle: 'Crea un nuovo stato task.',
    editTitle: 'Modifica stato task',
    editSubtitle: 'Aggiorna lo stato task selezionato.',
    newTaskStatus: 'Nuovo stato',
    name: 'Nome',
    description: 'Descrizione',
    color: 'Colore',
    icon: 'Icona',
    group: {
      label: 'Fase',
      open: 'Aperto',
      pending: 'In pending',
      in_validation: 'Da validare',
      closed_positive: 'Chiuso con esito positivo',
      closed_negative: 'Chiuso con esito negativo',
    },
    isActive: 'Attivo',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato Task creato correttamente.',
    updated: 'Stato Task aggiornato correttamente.',
    deleted: 'Stato Task eliminato correttamente.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    descriptionMax: 'La descrizione può contenere al massimo 500 caratteri.',
    colorRequired: 'Il colore è obbligatorio.',
    colorInvalid: 'Scegli un colore dalla palette.',
    iconInvalid: "Scegli un'icona dal catalogo.",
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare lo stato task. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato task.',
    deleteInUse: 'Questo stato task è usato da un task e non può essere eliminato.',
    completionPercentage: 'Percentuale di completamento',
    completionPercentageRequired: 'La percentuale di completamento è obbligatoria.',
    completionPercentageInvalid: 'La percentuale deve essere un numero intero.',
    completionPercentageRange: 'La percentuale deve essere compresa tra 0 e 100.',
    hints: {
      // Nomina di proposito QUATTRO campi: il messaggio del backend dice
      // ancora "only name and color" ed è rimasto invariato per non rompere
      // AC-048, ma il modulo non lo mostra. Questa è la dicitura corretta.
      systemStatusFields:
        'Uno stato di sistema ha campi fissi: puoi modificare solo nome, colore, icona e percentuale.',
    },
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, descrizione, colore, icona, fase e stato.',
      },
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina Stati Task',
    // Verificato su `TaskStatus::SYSTEM_HEAD_KEYS` (open) e
    // `SYSTEM_TAIL_KEYS` (closed_positive, closed_negative): uno fissato in
    // testa, due in coda (AC-047). Le ex chiavi di testa in_progress/pending/
    // in_validation ora sono fasi, portate da `group`, non righe di sistema.
    subtitle:
      'Trascina gli stati personalizzati per riordinarli. I tre stati di sistema restano fissi in testa e in coda.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: "Impossibile caricare l'elenco. Riprova.",
    saved: 'Ordine aggiornato correttamente.',
    forbidden: 'Non puoi riordinare queste righe.',
    // Maschile: "Stato Task", a differenza dei quattro lookup femminili.
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
    inactiveBadge: 'Disattivato',
  },
}
