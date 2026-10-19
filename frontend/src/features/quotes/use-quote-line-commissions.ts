import { useEffect, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { useOptionalConfirm } from '@/components/confirm-dialog-context'
import { fetchQuoteCommissionDefaults, fetchQuoteCommissionRecipients } from '@/features/quotes/api'
import type { QuoteLineFormValues } from '@/features/quotes/quote-schema'
import type { QuoteCommissionContext } from '@/features/quotes/types'
import { DEFAULT_LINE_QUANTITY, type LineCommissionDefaults } from '@/features/quotes/use-quote-lines-field'
import type { QuoteCatalogProduct, QuoteLineCommissionsAdapter } from '@/features/quotes/line-editor/types'

type CommissionDefaultsResponse = Awaited<ReturnType<typeof fetchQuoteCommissionDefaults>>
type ChangedRole = 'COMMERCIAL' | 'REPORTER' | 'SUPERVISOR'

/** What a pick brings when no role context is known: an empty block, as before. */
const NO_DEFAULTS: LineCommissionDefaults = { commissions: [], supplierCommissionDirection: null }

/** The defaults endpoint's rows in the form's own commission shape. */
function toFormCommissions(defaults: CommissionDefaultsResponse): NonNullable<QuoteLineFormValues['commissions']> {
  return defaults.commissions.map((commission) => ({
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
}

function roleScope(context: QuoteCommissionContext, productId: number) {
  return {
    ...(context.quoteId ? { quote_id: context.quoteId } : {}),
    product_id: productId,
    commercial_id: context.commercialId,
    reporter_id: context.reporterId,
    supervisor_id: context.supervisorId,
  }
}

function changedRoles(previous: QuoteCommissionContext, next: QuoteCommissionContext): ChangedRole[] {
  return [
    previous.commercialId !== next.commercialId ? 'COMMERCIAL' : null,
    previous.reporterId !== next.reporterId ? 'REPORTER' : null,
    previous.supervisorId !== next.supervisorId ? 'SUPERVISOR' : null,
  ].filter((role): role is ChangedRole => role !== null)
}

/**
 * The row's commissions after a role change: a retained commission follows
 * its role to the new holder, one whose role lost its holder is dropped (no
 * one may be paid for it), and the changed roles gain their defaults.
 */
async function resyncRow(row: QuoteLineFormValues, context: QuoteCommissionContext, roles: ChangedRole[]) {
  if (row.product_id === null) {
    return row
  }
  const scope = roleScope(context, row.product_id)
  const [recipients, defaults] = await Promise.all([
    fetchQuoteCommissionRecipients(scope),
    fetchQuoteCommissionDefaults({ ...scope, line_net_amount: (row.quantity ?? 0) * (row.unit_price ?? 0) }),
  ])
  const retained = (row.commissions ?? []).flatMap((commission) => {
    const recipient = recipients[commission.recipient_role]
    if (!recipient) return []
    if (recipient.id === commission.recipient_id && recipient.type === commission.recipient_type) {
      return [commission]
    }
    return [{ ...commission, recipient_type: recipient.type, recipient_id: recipient.id, recipient: { id: recipient.id, name: recipient.name } }]
  })
  const existingRoles = new Set(retained.map((commission) => commission.recipient_role))
  const additions = toFormCommissions(defaults).filter(
    (commission) => roles.includes(commission.recipient_role as ChangedRole) && !existingRoles.has(commission.recipient_role),
  )
  return { ...row, commissions: [...retained, ...additions] }
}

interface UseQuoteLineCommissionsArgs {
  value: QuoteLineFormValues[]
  onChange: (rows: QuoteLineFormValues[]) => void
  /** The quote's live role selections; absent = picks carry an empty block. */
  commissionContext?: QuoteCommissionContext
}

/**
 * The commission behaviour of the Offerte revenue rows (spec 0269 D-4/D-6),
 * moved out of the former `QuoteLinesField` unchanged: defaults on every
 * pick, a confirmed regeneration when a row changes product, and the re-sync
 * of every row when a role changes holder.
 */
export function useQuoteLineCommissions({
  value,
  onChange,
  commissionContext,
}: UseQuoteLineCommissionsArgs): QuoteLineCommissionsAdapter {
  const { t } = useTranslation()
  const confirm = useOptionalConfirm()
  const previousContext = useRef(commissionContext)

  // Step 1: a role changing holder re-syncs the commissions of every row.
  useEffect(() => {
    const previous = previousContext.current
    previousContext.current = commissionContext
    if (!previous || !commissionContext) return
    const roles = changedRoles(previous, commissionContext)
    if (roles.length === 0) return

    void Promise.all(value.map((row) => resyncRow(row, commissionContext, roles)))
      .then(onChange)
      .catch(() => toast.error(t('quotes.form.commissions.defaultsError')))
  }, [commissionContext, onChange, t, value])

  return useMemo(() => {
    const defaultsFor = async (productId: number, lineNetAmount: number): Promise<LineCommissionDefaults> => {
      if (!commissionContext) {
        return NO_DEFAULTS
      }
      const defaults = await fetchQuoteCommissionDefaults({
        ...roleScope(commissionContext, productId),
        line_net_amount: lineNetAmount,
      })
      return { commissions: toFormCommissions(defaults), supplierCommissionDirection: defaults.supplier_commission_direction }
    }
    const unitPriceOf = (item: QuoteCatalogProduct) => (item.meta.price === null ? 0 : Number(item.meta.price))

    return {
      // Step 2: new rows, all or nothing (D-6).
      defaultsForNewRows: async (items) => {
        try {
          const resolved = await Promise.all(
            items.map(async (item) => [item.id, await defaultsFor(item.id, DEFAULT_LINE_QUANTITY * unitPriceOf(item))] as const),
          )
          return new Map(resolved)
        } catch {
          toast.error(t('quotes.form.commissions.defaultsError'))
          return null
        }
      },
      // Step 3: a product change replaces the commission snapshots, confirmed
      // first; fail closed when the app-level confirmation is unavailable.
      defaultsForReplacement: async (row, item) => {
        if (row.product_id !== null && row.product_id !== item.id) {
          const accepted =
            confirm !== null &&
            (await confirm({
              tone: 'destructive',
              title: t('quotes.form.commissions.regenerateTitle'),
              description: t('quotes.form.commissions.regenerateDescription'),
              confirmLabel: t('quotes.form.commissions.regenerateConfirm'),
              cancelLabel: t('common.cancel'),
            }))
          if (!accepted) return null
        }
        try {
          return await defaultsFor(item.id, (row.quantity ?? 0) * unitPriceOf(item))
        } catch {
          toast.error(t('quotes.form.commissions.defaultsError'))
          return null
        }
      },
    }
  }, [commissionContext, confirm, t])
}
