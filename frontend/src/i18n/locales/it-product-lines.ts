/**
 * Dominio condiviso delle righe funzione/categoria (spec 0057): l'editor
 * riga funzione-aziendale + categoria-prodotto usato sia dal form
 * opportunità sia da quello di gestione richieste (`ProductLinesField`,
 * contratto congelato). File satellite per mantenere `it.ts` entro i limiti
 * dimensionali (vedi `.claude/rules/engineering.md` §6).
 */

export const productLines = {
  rowLabel: 'Riga {{n}}',
  businessFunction: 'Funzione aziendale {{n}}',
  category: 'Categoria prodotto {{n}}',
  add: 'Aggiungi riga prodotto',
  remove: 'Rimuovi riga prodotto',
  hint: 'Ogni riga collega una funzione aziendale a una categoria prodotto: scegli prima la funzione, poi la categoria (filtrata di conseguenza). Rimuovi una riga con il cestino.',
  required: 'Aggiungi almeno una funzione aziendale con la relativa categoria prodotto.',
  rowIncomplete: 'Ogni riga richiede sia la funzione aziendale sia la categoria prodotto.',
  businessFunctionSearch: 'Cerca funzioni aziendali…',
  productCategorySearch: 'Cerca categorie prodotto…',
  selectPlaceholder: 'Seleziona…',
  selectEmpty: 'Nessun risultato trovato.',
  selectError: 'Impossibile caricare le opzioni.',
  /** Spec 0129 D-5: nome accessibile per riga della checkbox "Tutte" (variante competenza). */
  allCategories: 'Tutte le categorie, riga {{n}}',
  /** Etichetta visibile accanto alla checkbox. */
  allCategoriesShort: 'Tutte',
  /** Spec 0129 D-3: resa in sola lettura di una riga con categoria nulla. */
  allCategoriesReadOnly: 'Tutte le categorie',
}
