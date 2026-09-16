/**
 * Modulo Import (ora un modulo top-level a sé stante, `/imports*` — non più
 * raggiungibile dal modulo Lead): storico reso dalla tabella generica
 * (dominio `import-runs`) e pagina di dettaglio del singolo run. Estratto in
 * un file affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6). Le etichette delle colonne/azioni della
 * tabella sono risolte dal motore tabellare generico via `t(column.label)`.
 */

export const leadImports = {
  forbidden: 'Non hai i permessi per visualizzare gli import.',
  newImport: 'Nuovo import',
  columns: {
    date: 'Data',
    operator: 'Operatore',
    file: 'File',
    records: 'Record',
    imported: 'Importati',
    errors: 'Errori',
    status: 'Stato',
  },
  actions: {
    view: 'Apri',
    delete: 'Elimina',
  },
  deleted: 'Import eliminato con successo.',
  deleteError: "Impossibile eliminare l'import. Riprova.",
  deleteForbidden: 'Non puoi eliminare questo import.',
  detail: {
    resume: 'Riprendi import',
    loadError: "Impossibile caricare l'import. Riprova.",
    sections: {
      stats: 'Statistiche',
      metadata: 'Metadati',
      errors: 'Errori',
      records: 'Record',
    },
    stats: {
      total: 'Righe totali',
      imported: 'Importati',
      modified: 'Aggiornati',
      invalid: 'Errori',
      warning: 'Avvisi',
      duplicate: 'Duplicati',
    },
    metadata: {
      file: 'File',
      globalConfig: 'Configurazione globale',
      dedupStrategy: 'Strategia duplicati',
      mappedColumns: 'Colonne mappate',
      noMetadata: 'Nessun metadato disponibile per questo import.',
    },
    gridLabel: 'Record importati (sola lettura)',
  },
}
