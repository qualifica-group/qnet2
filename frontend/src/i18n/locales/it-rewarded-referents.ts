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
    activeRewardsCount: 'Buoni attivi',
    completedRewardsCount: 'Buoni completati',
    lastAssignedAt: 'Ultima assegnazione',
  },
  advancedFilters: {
    rewardType: 'Tipologia di buono',
    opportunity: 'Opportunità',
    opportunityStatus: 'Stato commerciale',
    workflowStatus: 'Stato di lavorazione',
    operator: 'Operatore',
    assignedAt: 'Data assegnazione',
  },
  detail: {
    loadError: 'Impossibile caricare i buoni di questo referente. Riprova.',
    empty: 'Nessun buono trovato per questo referente.',
    assignedAt: 'Assegnato il',
    sourceRemoved: 'Origine non più disponibile',
    client: 'Cliente',
    categories: 'Categorie prodotto',
    commercialStatus: 'Stato commerciale',
    workflowStatus: 'Stato di lavorazione',
    operator: 'Operatore',
  },
}
