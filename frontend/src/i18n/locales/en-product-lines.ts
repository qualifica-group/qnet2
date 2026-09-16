/**
 * Shared product-lines domain (spec 0057, spec 0132): two distinct editors on
 * the same i18n namespace — `ProductLinesField` (card contract: parent
 * category then category) and `CompetenceLinesField` (competence contract:
 * business function then category, unchanged). Sibling file so `en.ts` stays
 * within the engineering size limits (see `.claude/rules/engineering.md`
 * §6).
 */

export const productLines = {
  rowLabel: 'Row {{n}}',
  /** First select of the CARD field (spec 0132 D-1): the root category, with no parent. */
  rootCategory: 'Parent category {{n}}',
  businessFunction: 'Business function {{n}}',
  category: 'Product category {{n}}',
  add: 'Add product line',
  remove: 'Remove product line',
  hint: 'Each row links one parent category to one of its descendant categories: pick the parent category first, then the category (scoped accordingly). Remove a row with the trash icon.',
  required: 'Add at least one parent category with its product category.',
  rowIncomplete: 'Each row requires both a parent category and a product category.',
  businessFunctionSearch: 'Search business functions…',
  productCategorySearch: 'Search product categories…',
  rootCategorySearch: 'Search parent category…',
  selectPlaceholder: 'Select…',
  selectEmpty: 'No results found.',
  selectError: 'Unable to load the options.',
  /** Spec 0129 D-5: per-row accessible name of the "All categories" checkbox (CompetenceLinesField). */
  allCategories: 'All categories, row {{n}}',
  /** Visible label next to the checkbox. */
  allCategoriesShort: 'All',
  /** Spec 0129 D-3: read-only rendering of a row with a null category. */
  allCategoriesReadOnly: 'All categories',
  /** Spec 0132 D-5: derived-function label in a CARD row's detail rendering. */
  functionReadOnly: 'function: {{name}}',
}
