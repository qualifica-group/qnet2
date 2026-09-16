/**
 * Dominio condiviso delle righe prodotto (spec 0057, spec 0132): due editor
 * distinti sullo stesso namespace i18n — `ProductLinesField` (contratto
 * card: categoria genitore poi categoria) e `CompetenceLinesField`
 * (contratto competenza: funzione aziendale poi categoria, invariato). File
 * satellite per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6).
 */

export const productLines = {
  rowLabel: 'Riga {{n}}',
  /** Primo select del campo CARD (spec 0132 D-1): la categoria root, senza parent. */
  rootCategory: 'Categoria genitore {{n}}',
  businessFunction: 'Funzione aziendale {{n}}',
  category: 'Categoria prodotto {{n}}',
  add: 'Aggiungi riga prodotto',
  remove: 'Rimuovi riga prodotto',
  hint: 'Ogni riga collega una categoria genitore a una sua categoria discendente: scegli prima la categoria genitore, poi la categoria (filtrata di conseguenza). Rimuovi una riga con il cestino.',
  required: 'Aggiungi almeno una categoria genitore con la relativa categoria prodotto.',
  rowIncomplete: 'Ogni riga richiede sia la categoria genitore sia la categoria prodotto.',
  businessFunctionSearch: 'Cerca funzioni aziendali…',
  productCategorySearch: 'Cerca categorie prodotto…',
  rootCategorySearch: 'Cerca categoria genitore…',
  selectPlaceholder: 'Seleziona…',
  selectEmpty: 'Nessun risultato trovato.',
  selectError: 'Impossibile caricare le opzioni.',
  /** Spec 0129 D-5: nome accessibile per riga della checkbox "Tutte" (CompetenceLinesField). */
  allCategories: 'Tutte le categorie, riga {{n}}',
  /** Etichetta visibile accanto alla checkbox. */
  allCategoriesShort: 'Tutte',
  /** Spec 0129 D-3: resa in sola lettura di una riga con categoria nulla. */
  allCategoriesReadOnly: 'Tutte le categorie',
  /** Spec 0132 D-5: etichetta della funzione derivata nella resa di dettaglio di una riga CARD. */
  functionReadOnly: 'funzione: {{name}}',
}
