import type { TFunction } from 'i18next'

/**
 * The document-layouts for-select endpoint requires `module` (spec 0069):
 * scopes the list — and the create form's resolved default — to `quotes`.
 * Hoisted so the object identity is stable across renders.
 */
export const QUOTES_LAYOUT_MODULE_PARAM = { module: 'quotes' } as const

/** The shared strings of every relation picker of the offer. */
export function quoteRelationLabels(t: TFunction) {
  return {
    placeholder: t('quotes.form.selectPlaceholder'),
    emptyLabel: t('quotes.form.selectEmpty'),
    errorLabel: t('quotes.form.selectError'),
    clearLabel: t('common.clear'),
    retryLabel: t('common.retry'),
  }
}
