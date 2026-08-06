/**
 * Dominio Note (spec 0052): feature agnostica di note collaborative con
 * menzioni, montata da qualunque modulo host tramite `NotesSection`. File
 * satellite per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6).
 */

export const notes = {
  scope: {
    all: 'Tutte le note',
    general: 'Note generali',
    filterLabel: 'Filtra le note per offerta',
    targetLabel: 'Destinazione della nota',
    generalBadge: 'Generale',
  },
  section: {
    title: 'Note',
    description: 'Discuti il record con i colleghi: usa @ per menzionarli.',
    loadError: 'Impossibile caricare le note.',
    empty: 'Nessuna nota. Scrivi la prima per iniziare la discussione.',
  },
  list: {
    loadMore: 'Carica altre',
    replyCount_one: '{{count}} risposta',
    replyCount_other: '{{count}} risposte',
  },
  item: {
    edited: '(modificato)',
    replyAction: 'Rispondi',
    editAction: 'Modifica nota',
    deleteAction: 'Elimina nota',
    deleteConfirm: "La nota sparirà dall'elenco. Le eventuali risposte restano nascoste insieme ad essa.",
  },
  composer: {
    placeholder: 'Scrivi una nota, usa @ per menzionare un collega…',
    bodyRequired: 'Scrivi qualcosa prima di inviare.',
    bodyTooLong: 'La nota può contenere al massimo {{count}} caratteri.',
    genericError: 'Invio non riuscito. Riprova.',
    send: 'Invia',
    save: 'Salva',
    hint: 'Digita @ per menzionare un collega',
    removeMention: 'Rimuovi la menzione di {{name}}',
    charactersLeft_one: '{{count}} carattere rimasto',
    charactersLeft_other: '{{count}} caratteri rimasti',
  },
  mentionPicker: {
    label: 'Utenti selezionabili',
    title: 'Menziona un collega',
    hint: 'Tab o Invio per inserire',
    empty: 'Nessun utente corrispondente',
  },
}
