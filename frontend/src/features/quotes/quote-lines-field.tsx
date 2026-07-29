/* eslint-disable react-refresh/only-export-components -- small dedupe helpers (`knownProductsFrom`/`knownVatRatesFrom`) shared by both tab files, colocated with the row editor they feed rather than split into a components-only file for two 5-line pure functions */
import { useEffect, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { useQuoteLinesField } from '@/features/quotes/use-quote-lines-field'
import { QuoteLineRow, type QuoteLineRowErrors } from '@/features/quotes/quote-line-row'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteLine, QuoteLineProductRef, QuoteLineVatRateRef } from '@/features/quotes/types'
import { fetchQuoteCommissionDefaults } from '@/features/quotes/api'
import { useOptionalConfirm } from '@/components/confirm-dialog-context'
import { quoteLineGridClass, quoteLineMinWidthClass } from './quote-line-grid'

/** Dedupe by id: several persisted rows may point at the same product. */
export function knownProductsFrom(lines: QuoteLine[]): QuoteLineProductRef[] {
  const byId = new Map<number, QuoteLineProductRef>()
  for (const line of lines) {
    byId.set(line.product.id, line.product)
  }
  return [...byId.values()]
}

/** Dedupe by id: several persisted rows may share the same VAT rate. */
export function knownVatRatesFrom(lines: QuoteLine[]): QuoteLineVatRateRef[] {
  const byId = new Map<number, QuoteLineVatRateRef>()
  for (const line of lines) {
    if (line.vat_rate) {
      byId.set(line.vat_rate.id, line.vat_rate)
    }
  }
  return [...byId.values()]
}

interface QuoteLinesFieldProps {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  variant: 'revenue' | 'cost'
  disabled: boolean
  /** `undefined` = unfiltered (Cost tab always; Offer tab once unlocked). */
  categoryIds?: number[]
  errors?: (QuoteLineRowErrors | undefined)[]
  knownProducts: QuoteLineProductRef[]
  knownVatRates: QuoteLineVatRateRef[]
  vatRatePercentFor: (vatRateId: number) => number | null
  rememberVatRatePercent: (vatRateId: number, percent: number) => void
  commissionContext?: {
    quoteId?: number
    commercialId: number | null
    reporterId: number | null
    supervisorId: number | null
  }
}

/**
 * One tab's (`offer_lines`/`cost_lines`) repeatable row editor (D-11: both
 * tabs share this exact component, `variant` only changes which product price
 * precompiles `unit_price`, D-6). Horizontally scrollable so the 8-column
 * grid never forces the page itself to scroll (ui-design.md §3).
 */
export function QuoteLinesField({
  value,
  onChange,
  variant,
  disabled,
  categoryIds,
  errors,
  knownProducts,
  knownVatRates,
  vatRatePercentFor,
  rememberVatRatePercent,
  commissionContext,
}: QuoteLinesFieldProps) {
  const { t } = useTranslation()
  const confirm = useOptionalConfirm()
  const previousRecipients = useRef(commissionContext)
  const { addRow, removeRow, setField, setProduct } = useQuoteLinesField({
    value,
    onChange,
    variant,
    rememberVatRatePercent,
  })

  const productById = (id: number | null) => (id === null ? undefined : knownProducts.find((p) => p.id === id))
  const vatRateById = (id: number | null) => (id === null ? undefined : knownVatRates.find((v) => v.id === id))

  useEffect(() => {
    const previous = previousRecipients.current
    previousRecipients.current = commissionContext
    if (variant !== 'revenue' || !previous || !commissionContext) return
    const changedRoles = [
      previous.commercialId !== commissionContext.commercialId ? 'COMMERCIAL' : null,
      previous.reporterId !== commissionContext.reporterId ? 'REPORTER' : null,
      previous.supervisorId !== commissionContext.supervisorId ? 'SUPERVISOR' : null,
    ].filter((role): role is 'COMMERCIAL' | 'REPORTER' | 'SUPERVISOR' => role !== null)
    if (changedRoles.length === 0) return

    void Promise.all(value.map(async (row) => {
      if (row.product_id === null) return row
      const withoutMissingRecipients = (row.commissions ?? []).filter((commission) => {
        if (commission.recipient_role === 'COMMERCIAL') return commissionContext.commercialId !== null
        if (commission.recipient_role === 'REPORTER') return commissionContext.reporterId !== null
        if (commission.recipient_role === 'SUPERVISOR') return commissionContext.supervisorId !== null
        return true
      })
      const defaults = await fetchQuoteCommissionDefaults({
        ...(commissionContext.quoteId ? { quote_id: commissionContext.quoteId } : {}),
        product_id: row.product_id,
        line_net_amount: (row.quantity ?? 0) * (row.unit_price ?? 0),
        commercial_id: commissionContext.commercialId,
        reporter_id: commissionContext.reporterId,
        supervisor_id: commissionContext.supervisorId,
      })
      const existingRoles = new Set(withoutMissingRecipients.map((commission) => commission.recipient_role))
      const additions = defaults
        .filter((commission) => changedRoles.includes(commission.recipient_role as typeof changedRoles[number]) && !existingRoles.has(commission.recipient_role))
        .map((commission) => ({
          id: commission.id,
          recipient_role: commission.recipient_role,
          recipient_type: commission.recipient_type,
          recipient_id: commission.recipient_id,
          recipient: commission.recipient,
          commission_type: commission.commission_type,
          value: Number(commission.value),
          internal_note: commission.internal_note,
          origin: commission.origin,
          commission_configuration_id: commission.commission_configuration_id,
        }))
      return { ...row, commissions: [...withoutMissingRecipients, ...additions] }
    })).then(onChange).catch(() => toast.error(t('quotes.form.commissions.defaultsError')))
  }, [commissionContext, onChange, t, value, variant])
  const changeRevenueProduct = async (
    index: number,
    productId: number | null,
    item: Parameters<typeof setProduct>[2],
  ): Promise<boolean> => {
    const row = value[index]
    if (variant === 'revenue' && row.product_id !== null && productId !== row.product_id) {
      // A revenue product change replaces its commission snapshots. Fail closed
      // when the app-level confirmation service is unavailable.
      if (!confirm) return false
      const accepted = await confirm({
        tone: 'destructive',
        title: t('quotes.form.commissions.regenerateTitle'),
        description: t('quotes.form.commissions.regenerateDescription'),
        confirmLabel: t('quotes.form.commissions.regenerateConfirm'),
        cancelLabel: t('common.cancel'),
      })
      if (!accepted) return false
    }
    if (productId === null || !item || variant === 'cost' || !commissionContext) {
      setProduct(index, productId, item, variant === 'revenue' ? [] : undefined)
      return true
    }
    const unitPrice = item.meta.price === null ? 0 : Number(item.meta.price)
    try {
      const defaults = await fetchQuoteCommissionDefaults({
        ...(commissionContext.quoteId ? { quote_id: commissionContext.quoteId } : {}),
        product_id: productId,
        line_net_amount: (row.quantity ?? 0) * unitPrice,
        commercial_id: commissionContext.commercialId,
        reporter_id: commissionContext.reporterId,
        supervisor_id: commissionContext.supervisorId,
      })
      setProduct(index, productId, item, defaults.map((commission) => ({
        id: commission.id,
        recipient_role: commission.recipient_role,
        recipient_type: commission.recipient_type,
        recipient_id: commission.recipient_id,
        recipient: commission.recipient,
        commission_type: commission.commission_type,
        value: Number(commission.value),
        internal_note: commission.internal_note,
        origin: commission.origin,
        commission_configuration_id: commission.commission_configuration_id,
      })))
      return true
    } catch {
      toast.error(t('quotes.form.commissions.defaultsError'))
      return false
    }
  }

  return (
    <div className="flex flex-col gap-2">
      <div className="overflow-x-auto rounded-lg border bg-surface">
        <div className={quoteLineMinWidthClass(variant)}>
          <div className={`${quoteLineGridClass(variant)} border-b bg-muted/40 px-2 py-1.5 text-[11px] font-medium text-muted-foreground`}>
            <span>{t('quotes.form.lineProductHeader')}</span>
            <span>{t('quotes.form.lineCodeHeader')}</span>
            <span>{t('quotes.form.lineQuantityHeader')}</span>
            <span>{t('quotes.form.lineUnitPriceHeader')}</span>
            <span>{t('quotes.form.lineVatRateHeader')}</span>
            <span className="text-right">{t('quotes.form.lineNetHeader')}</span>
            <span className="text-right">{t('quotes.form.lineVatHeader')}</span>
            <span className="text-right">{t('quotes.form.lineTotalHeader')}</span>
            {variant === 'revenue' ? <span className="sr-only">{t('quotes.form.commissions.header')}</span> : null}
            <span className="sr-only">{t('quotes.form.lineRemoveHeader')}</span>
          </div>

          {value.length === 0 ? (
            <p className="px-2 py-3 text-xs text-muted-foreground">{t('quotes.form.linesEmpty')}</p>
          ) : (
            value.map((row, index) => (
              // The row's identity IS its position (mirrors `ProductLinesField`/`ManagerSlotsField`).
              <QuoteLineRow
                key={index}
                index={index}
                row={row}
                disabled={disabled}
                categoryIds={categoryIds}
                knownProduct={productById(row.product_id)}
                knownVatRate={vatRateById(row.vat_rate_id)}
                vatRatePercentFor={vatRatePercentFor}
                rememberVatRatePercent={rememberVatRatePercent}
                error={errors?.[index]}
                variant={variant}
                onChangeProduct={(productId, item) => changeRevenueProduct(index, productId, item)}
                onChangeField={(patch) => setField(index, patch)}
                onRemove={() => removeRow(index)}
              />
            ))
          )}
        </div>
      </div>

      <Button
        type="button"
        variant="outline"
        size="sm"
        disabled={disabled}
        onClick={addRow}
        className="w-full justify-center border-dashed text-muted-foreground hover:border-solid hover:text-foreground"
      >
        <Plus aria-hidden="true" className="size-3.5" />
        {t('quotes.form.lineAdd')}
      </Button>
    </div>
  )
}
