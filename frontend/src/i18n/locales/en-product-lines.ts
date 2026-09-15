/**
 * Shared product-lines field domain (spec 0057): the business-function +
 * product-category row editor consumed by both the opportunities and
 * request-management create forms (`ProductLinesField`, frozen contract).
 * Sibling file so `en.ts` stays within the engineering size limits (see
 * `.claude/rules/engineering.md` §6).
 */

export const productLines = {
  rowLabel: 'Row {{n}}',
  businessFunction: 'Business function {{n}}',
  category: 'Product category {{n}}',
  add: 'Add product line',
  remove: 'Remove product line',
  hint: 'Each row links one business function to one product category: pick the function first, then the category (scoped accordingly). Remove a row with the trash icon.',
  required: 'Add at least one business function with its product category.',
  rowIncomplete: 'Each row requires both a business function and a product category.',
  businessFunctionSearch: 'Search business functions…',
  productCategorySearch: 'Search product categories…',
  selectPlaceholder: 'Select…',
  selectEmpty: 'No results found.',
  selectError: 'Unable to load the options.',
  /** Spec 0129 D-5: per-row accessible name of the "All categories" checkbox (competence variant). */
  allCategories: 'All categories, row {{n}}',
  /** Visible label next to the checkbox. */
  allCategoriesShort: 'All',
  /** Spec 0129 D-3: read-only rendering of a row with a null category. */
  allCategoriesReadOnly: 'All categories',
}
