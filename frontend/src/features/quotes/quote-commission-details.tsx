import { useId } from 'react'
import { useTranslation } from 'react-i18next'
import { CircleHelp, HandCoins } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { CalculationHint } from '@/components/calculation-hint'
import { FieldHint } from '@/components/field-hint'
import { commissionAmountHint, sumHint } from '@/features/quotes/quote-calculation-hints'
import { QuoteCommissionRecipient } from '@/features/quotes/quote-commission-recipient'
import { TruncatedLabel } from '@/features/quotes/quote-truncated-label'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { round2 } from '@/features/quotes/quote-totals'
import type { CommissionRole, SupplierCommissionDirection } from '@/features/commission-configurations/types'
import type { QuoteLine, QuoteLineCommission } from '@/features/quotes/types'

const ROLES: CommissionRole[] = ['COMMERCIAL', 'REPORTER', 'SUPERVISOR', 'SUPPLIER']

/** Commission percentages are stored with 4 decimals; 2 are enough to read them. */
const PERCENTAGE_DIGITS = 2

/** `offer_line_id -> imputed cost net`, the persisted cost rows bucketed onto the row they are imputed to. */
function allocatedCostNetByOfferLineId(costLines: QuoteLine[]): Map<number, number> {
  const totals = new Map<number, number>()
  for (const cost of costLines) {
    if (cost.offer_line_id != null) {
      totals.set(cost.offer_line_id, round2((totals.get(cost.offer_line_id) ?? 0) + Number(cost.net_amount)))
    }
  }
  return totals
}

/** The persisted calculated amount of a set of commissions. */
function amountOf(commissions: QuoteLineCommission[]): number {
  return round2(commissions.reduce((sum, commission) => sum + Number(commission.calculated_amount), 0))
}

interface CommissionCellProps {
  commissions: QuoteLineCommission[]
  role: CommissionRole
  lineNet: number
  allocatedCostNet: number
  /** The Supplier commission's frozen direction, shown as a badge; undefined for the other roles. */
  direction?: QuoteLine['supplier_commission_direction']
}

/** "40%" in the UI locale, or the fixed-amount label. */
function useCommissionValueLabel() {
  const { t } = useTranslation()
  return (commission: QuoteLineCommission) =>
    commission.commission_type === 'PERCENTAGE'
      ? `${new Intl.NumberFormat(i18n.language, { maximumFractionDigits: PERCENTAGE_DIGITS }).format(Number(commission.value))}%`
      : t('commissionConfigurations.options.commission_type.FIXED_AMOUNT')
}

/** Received or Paid, with a "?" explaining what it means for this row. */
function DirectionBadge({ direction }: { direction: SupplierCommissionDirection }) {
  const { t } = useTranslation()
  const label = t(`commissionConfigurations.options.supplier_commission_direction.${direction}`)

  return (
    <div className="flex items-center gap-1">
      <Badge variant={direction === 'RECEIVED' ? 'secondary' : 'outline'} className="h-4 px-1.5 text-[10px]">
        {label}
      </Badge>
      <FieldHint
        icon={CircleHelp}
        text={t(`quotes.detail.commissionDetails.directionHints.${direction}`)}
        label={t('quotes.detail.commissionDetails.directionHintLabel', { direction: label })}
      />
    </div>
  )
}

/** One role of one row: the recipient on top, then value and amount on one line, the Supplier direction last. */
function CommissionCell({ commissions, role, lineNet, allocatedCostNet, direction }: CommissionCellProps) {
  const { t } = useTranslation()
  const valueLabel = useCommissionValueLabel()

  if (commissions.length === 0) {
    return <div className="text-right text-muted-foreground">—</div>
  }

  return (
    <div className="flex min-w-0 flex-col gap-1">
      {commissions.map((commission, index) => (
        <div key={commission.id ?? index} className="flex min-w-0 flex-col">
          <QuoteCommissionRecipient recipient={commission.recipient} type={commission.recipient_type} />
          <div className="flex items-baseline justify-between gap-2 tabular-nums">
            <span className="text-muted-foreground">{valueLabel(commission)}</span>
            <CalculationHint
              className="font-medium text-foreground"
              {...commissionAmountHint(
                {
                  commission,
                  title: `${t(`quotes.form.commissions.roles.${role}`)} · ${commission.recipient?.name ?? '—'}`,
                  lineNet,
                  allocatedCostNet,
                },
                t,
              )}
            >
              {formatQuoteAmount(Number(commission.calculated_amount))}
            </CalculationHint>
          </div>
        </div>
      ))}
      {direction ? <DirectionBadge direction={direction} /> : null}
    </div>
  )
}

/**
 * "Dettaglio provvigioni per prodotto" (user directive 2026-10-23, the legacy
 * "Dettaglio Provvigioni per Servizio"): in the quote summary, behind its own
 * toggle like the advanced data, one row per
 * product line that carries commissions, the recipient, value and persisted
 * amount of each role, the Supplier one with its direction, and the totals.
 * Read-only; the caller renders it only with the `commissions` permission.
 */
interface QuoteCommissionDetailsProps {
  lines: QuoteLine[]
  /** The cost rows: their imputed net is the part of each row's net a percentage is not calculated on. */
  costLines: QuoteLine[]
}

export function QuoteCommissionDetails({ lines, costLines }: QuoteCommissionDetailsProps) {
  const { t } = useTranslation()
  const titleId = useId()
  const allocatedCostNetById = allocatedCostNetByOfferLineId(costLines)
  const rows = lines
    .map((line, index) => ({ line, rowNumber: index + 1, commissions: line.commissions ?? [] }))
    .filter((row) => row.commissions.length > 0)

  if (rows.length === 0) {
    return null
  }

  const byRole = (commissions: QuoteLineCommission[], role: CommissionRole) =>
    commissions.filter((commission) => commission.recipient_role === role)
  const allCommissions = rows.flatMap((row) => row.commissions)

  return (
    <div className="rounded-md border bg-card p-3">
      <div id={titleId} className="mb-2 flex items-center gap-1.5 text-xs font-semibold text-foreground">
        <HandCoins aria-hidden="true" className="size-3.5" />
        {t('quotes.detail.commissionDetails.title')}
      </div>
      <div className="overflow-x-auto">
        <table aria-labelledby={titleId} className="w-full min-w-[720px] table-fixed text-xs">
          <thead>
            <tr className="border-b text-muted-foreground">
              <th scope="col" className="w-1/4 py-1 pr-2 text-left font-medium">
                {t('quotes.form.lineProductHeader')}
              </th>
              {ROLES.map((role) => (
                <th key={role} scope="col" className="py-1 pr-3 text-left font-medium">
                  {t(`quotes.form.commissions.roles.${role}`)}
                </th>
              ))}
              <th scope="col" className="w-24 py-1 text-right font-medium">
                {t('quotes.detail.commissionDetails.total')}
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map(({ line, rowNumber, commissions }) => (
              <tr key={line.id} className="border-b align-top">
                <td className="py-1.5 pr-3">
                  <TruncatedLabel
                    label={t('quotes.form.costsTab.associatedProductOption', { product: line.product.name, n: rowNumber })}
                  />
                </td>
                {ROLES.map((role) => (
                  <td key={role} className="py-1.5 pr-3">
                    <CommissionCell
                      commissions={byRole(commissions, role)}
                      role={role}
                      lineNet={Number(line.net_amount)}
                      allocatedCostNet={allocatedCostNetById.get(line.id) ?? 0}
                      direction={role === 'SUPPLIER' && byRole(commissions, role).length > 0 ? line.supplier_commission_direction : undefined}
                    />
                  </td>
                ))}
                <td className="py-1.5 text-right font-medium tabular-nums">
                  <CalculationHint
                    {...sumHint(
                      t('quotes.calculationHints.rowTotalTitle', { n: rowNumber }),
                      t('quotes.calculationHints.rowTotalRule'),
                      commissions.map((commission) => Number(commission.calculated_amount)),
                      amountOf(commissions),
                    )}
                  >
                    {formatQuoteAmount(amountOf(commissions))}
                  </CalculationHint>
                </td>
              </tr>
            ))}
            <tr>
              <td className="py-1.5 pr-3 font-medium">{t('quotes.detail.commissionDetails.total')}</td>
              {ROLES.map((role) => (
                <td key={role} className="py-1.5 pr-3 text-right font-medium tabular-nums">
                  <CalculationHint
                    {...sumHint(
                      t('quotes.calculationHints.roleTotalTitle', { role: t(`quotes.form.commissions.roles.${role}`) }),
                      t('quotes.calculationHints.roleTotalRule'),
                      byRole(allCommissions, role).map((commission) => Number(commission.calculated_amount)),
                      amountOf(byRole(allCommissions, role)),
                    )}
                  >
                    {formatQuoteAmount(amountOf(byRole(allCommissions, role)))}
                  </CalculationHint>
                </td>
              ))}
              <td className="py-1.5 text-right font-semibold tabular-nums">
                <CalculationHint
                  {...sumHint(
                    t('quotes.calculationHints.grandTotalTitle'),
                    t('quotes.calculationHints.grandTotalRule'),
                    ROLES.map((role) => amountOf(byRole(allCommissions, role))).filter((amount) => amount !== 0),
                    amountOf(allCommissions),
                  )}
                >
                  {formatQuoteAmount(amountOf(allCommissions))}
                </CalculationHint>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  )
}
