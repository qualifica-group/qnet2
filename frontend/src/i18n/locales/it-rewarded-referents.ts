/**
 * Dominio Referenti con Buoni (spec 0059). Modulo aggregato in sola lettura:
 * una riga per Referente con almeno un'assegnazione, riga espandibile
 * (master/detail) con il dettaglio di ogni buono. Estratto in un file
 * affiancato per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6).
 */

export const rewardedReferents = {
  title: 'Referenti con Buoni',
  subtitle: 'Elenco dei referenti con almeno un buono, premio o incentivo assegnato.',
  forbidden: 'Non hai i permessi per visualizzare i referenti con buoni.',
  columns: {
    name: 'Nome',
    registries: 'Anagrafiche collegate',
    email: 'Email',
    phone: 'Telefono',
    rewardsCount: 'Totale buoni',
    pendingRewardsCount: 'Buoni in pending',
    approvedRewardsCount: 'Buoni approvati',
    lastAssignedAt: 'Ultima assegnazione',
  },
  advancedFilters: {
    rewardType: 'Tipologia di buono',
    opportunity: 'Opportunità',
    quote: 'Offerta',
    opportunityStatus: 'Stato commerciale',
    workflowStatus: 'Stato di lavorazione',
    operator: 'Operatore',
    assignedAt: 'Data assegnazione',
    rewardStatus: 'Stato buono',
  },
  detail: {
    loadError: 'Impossibile caricare i buoni di questo referente. Riprova.',
    empty: 'Nessun buono trovato per questo referente.',
    assignedAt: 'Assegnato il',
    sourceRemoved: 'Origine non più disponibile',
    sourceTypes: {
      opportunity: 'Opportunità',
      quote: 'Offerta',
    },
    client: 'Cliente',
    categories: 'Categorie prodotto',
    commercialStatus: 'Stato commerciale',
    opportunityStatus: 'Stato opportunità',
    workflowStatus: 'Stato di lavorazione',
    operator: 'Operatore',
    status: 'Stato',
    statusPlaceholder: 'Seleziona uno stato',
    statusSearchPlaceholder: 'Cerca uno stato…',
    statusEmpty: 'Nessuno stato trovato',
    statusError: 'Impossibile caricare gli stati',
    statusClearLabel: 'Rimuovi stato',
    statusRetry: 'Riprova',
  },
}
