import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import type { ForSelectItem } from '@/features/for-select/types'

/** Resource segment of the dedicated for-select endpoint (D-8): `GET /api/quote-offer-lines/for-select`. */
const QUOTE_OFFER_LINES_FOR_SELECT_RESOURCE = 'quote-offer-lines'

interface WorkOrderQuoteLinesFieldProps {
  /** Selected quote line ids (controlled). */
  value: number[]
  onChange: (next: number[]) => void
  /** The linked offer's id (D-5): the picker's scope. `null` means no offer is chosen yet. */
  quoteId: number | null
  /**
   * The work order being EDITED, so its own already-programmed lines stay
   * offered by the picker (spec 0095 D-7): the endpoint now excludes every
   * line already used by another work order, and without this the current
   * work order's own selection would vanish from the search results (though
   * `selectedItems` keeps their badges visible). Omitted on create, where
   * there is no "own" work order yet.
   */
  exceptWorkOrderId?: number | null
  /** `{id, label}` of the already-selected lines, so a badge never falls back to `#id`. */
  selectedItems?: ForSelectItem[]
  disabled?: boolean
}

/** Stable empty default: an inline `[]` would be a new reference on every render. */
const EMPTY_ITEMS: ForSelectItem[] = []

/**
 * The "righe prodotto" picker of the work order form (AC-071/072), the same
 * shape `ProductsOfInterestField` established: an `AsyncPaginatedMultiSelect`
 * scoped to ONE offer via `quote_id` (D-8's `quote-offer-lines/for-select`,
 * REVENUE-only, server-enforced). Disabled until an offer is chosen — there
 * is no whole-catalogue escape, mirroring the products picker's `lockScope`.
 *
 * The other half of AC-072 (dropping the selection when the offer itself
 * changes) belongs to the caller that owns both fields (`useWorkOrderForm`'s
 * `handleQuoteChange`), not to this picker: every line belongs to exactly one
 * offer, so a offer change can never leave a valid survivor.
 */
export function WorkOrderQuoteLinesField({
  value,
  onChange,
  quoteId,
  exceptWorkOrderId,
  selectedItems = EMPTY_ITEMS,
  disabled = false,
}: WorkOrderQuoteLinesFieldProps) {
  const { t } = useTranslation()

  // Referentially stable, else every render would restart the paginated query.
  const params = useMemo(
    () => ({
      quote_id: quoteId ?? 0,
      ...(exceptWorkOrderId != null ? { except_work_order_id: exceptWorkOrderId } : {}),
    }),
    [quoteId, exceptWorkOrderId],
  )

  const withoutQuote = quoteId === null

  return (
    <div className="flex flex-col gap-2">
      <AsyncPaginatedMultiSelect
        resource={QUOTE_OFFER_LINES_FOR_SELECT_RESOURCE}
        value={value}
        onChange={onChange}
        selectedItems={selectedItems}
        params={params}
        disabled={disabled || withoutQuote}
        labels={{
          placeholder: t('workOrders.form.quoteLines.placeholder'),
          searchPlaceholder: t('workOrders.form.quoteLines.searchPlaceholder'),
          empty: t('workOrders.form.quoteLines.empty'),
          error: t('workOrders.form.quoteLines.error'),
          removeLabel: t('workOrders.form.quoteLines.remove'),
          triggerLabel: t('workOrders.form.quoteLines.fieldLabel'),
          retry: t('common.retry'),
        }}
      />

      <p className="text-xs text-muted-foreground">
        {withoutQuote
          ? t('workOrders.form.quoteLines.hintNoQuote')
          : t('workOrders.form.quoteLines.hintScoped')}
      </p>
    </div>
  )
}
