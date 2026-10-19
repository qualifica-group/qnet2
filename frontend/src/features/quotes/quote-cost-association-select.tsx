import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { SearchableSelect } from '@/components/ui/searchable-select'
import type { QuoteCostOfferLineOption } from '@/features/quotes/quote-line-types'

interface QuoteCostAssociationSelectProps {
  /** 1-based row number: the row's name in the labels. */
  position: number
  /** The OFFER row's `client_key` this cost is attributed to, or `null`. */
  value: string | null | undefined
  options: QuoteCostOfferLineOption[]
  disabled: boolean
  onChange: (offerLineKey: string | null) => void
}

/**
 * "Prodotto associato" of a cost row card (spec 0144 AC-011, moved to the
 * shared line editor by spec 0269): the live OFFER rows this cost may be
 * attributed to. The shared control speaks numeric ids, so options are
 * addressed by position while the row keeps the stable `client_key`.
 */
export function QuoteCostAssociationSelect({ position, value, options, disabled, onChange }: QuoteCostAssociationSelectProps) {
  const { t } = useTranslation()
  const selectOptions = useMemo(() => options.map((option, index) => ({ id: index, name: option.label })), [options])
  const selectedIndex = options.findIndex((option) => option.key === value)

  return (
    <SearchableSelect
      value={selectedIndex >= 0 ? selectedIndex : null}
      onChange={(index) => onChange(options[index]?.key ?? null)}
      onClear={() => onChange(null)}
      options={selectOptions}
      disabled={disabled}
      labels={{
        placeholder: t('quotes.form.costsTab.associatedProductNone'),
        searchPlaceholder: t('quotes.form.costsTab.associatedProductSearch'),
        empty: t('quotes.form.costsTab.associatedProductEmpty'),
        noMatch: t('quotes.form.costsTab.associatedProductEmpty'),
        error: t('quotes.form.costsTab.associatedProductEmpty'),
        retry: t('common.retry'),
        triggerLabel: t('quotes.form.costsTab.associatedProductLabel', { n: position }),
        clearLabel: t('common.clear'),
      }}
    />
  )
}
