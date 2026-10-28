import { useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { HandCoins } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { DetailEmpty } from '@/components/detail/detail-panel'
import {
  LINE_CARD_CLASS,
  LINE_CARD_FOOTER_CLASS,
  LINE_CARD_STRIP_CLASS,
  QuoteLineAmounts,
  QuoteLineCardHeading,
} from '@/features/quotes/line-editor/quote-line-card-parts'
import { QuoteLineEditorFrame } from '@/features/quotes/line-editor/quote-line-editor-frame'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { round2 } from '@/features/quotes/quote-totals'
import { QuoteCommissionsDialog } from './quote-commissions-dialog'
import type { QuoteLine } from '@/features/quotes/types'

type ReadOnlyVariant = 'revenue' | 'cost'

interface ReadOnlyCommissionsProps {
  line: QuoteLine
  position: number
  allocatedCostNet: number
}

/** The row's commissions, viewed in the same dialog the editor opens (read-only). */
function ReadOnlyCommissions({ line, position, allocatedCostNet }: ReadOnlyCommissionsProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)

  return (
    <>
      <Button
        type="button"
        variant="ghost"
        size="icon-sm"
        aria-label={t('quotes.form.commissions.action', { n: position })}
        onClick={() => setOpen(true)}
      >
        <HandCoins aria-hidden="true" />
      </Button>
      {open ? (
        <QuoteCommissionsDialog
          open={open}
          onOpenChange={setOpen}
          lineNumber={position}
          productName={line.product.name}
          productId={line.product_id}
          supplierId={line.supplier_id ?? null}
          quantity={Number(line.quantity)}
          unitPrice={Number(line.unit_price)}
          allocatedCostNet={allocatedCostNet}
          commissions={(line.commissions ?? []).map((commission) => ({
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
            supplier_commission_direction:
              commission.recipient_role === 'SUPPLIER' && commission.origin === 'MANUAL_OVERRIDE'
                ? (line.supplier_commission_direction ?? null)
                : undefined,
          }))}
          supplierCommissionDirection={line.supplier_commission_direction ?? null}
          disabled
          onSave={() => undefined}
        />
      ) : null}
    </>
  )
}

interface ReadOnlyFactProps {
  label: string
  children: ReactNode
}

/** One labelled value of the strip, where the editor has its input. */
function ReadOnlyFact({ label, children }: ReadOnlyFactProps) {
  return (
    <div className="flex min-w-0 flex-col">
      <dt className="text-muted-foreground">{label}</dt>
      <dd className="truncate">{children}</dd>
    </div>
  )
}

interface ReadOnlyLineProps {
  line: QuoteLine
  position: number
  showCommissions: boolean
  variant: ReadOnlyVariant
  /** Cost variant only: the associated OFFER row's label, or the "Costo generico" fallback. */
  associatedProductLabel?: string
  /** Spec 0145 (D-1), revenue variant only: this row's own imputed-cost net, feeds the commissions dialog's base (D-1/D-2). */
  allocatedCostNet: number
}

/**
 * One persisted row as the editor's card shows it (D-7: `product.code`/`name`
 * are live, amounts are frozen at save time, D-10): product on top, the values
 * and their result in the tinted strip, the additional description below.
 */
function ReadOnlyLine({ line, position, showCommissions, variant, associatedProductLabel, allocatedCostNet }: ReadOnlyLineProps) {
  const { t } = useTranslation()
  const unitSymbol = line.unit_of_measure?.symbol

  return (
    <li className={LINE_CARD_CLASS}>
      <QuoteLineCardHeading
        position={position}
        name={line.product.name}
        typology={line.product.product_typology}
        code={line.product.code}
        categoryPath={line.product.category?.name}
      >
        {showCommissions ? (
          <ReadOnlyCommissions line={line} position={position} allocatedCostNet={allocatedCostNet} />
        ) : null}
      </QuoteLineCardHeading>

      <div className={LINE_CARD_STRIP_CLASS}>
        <dl className="flex min-w-0 flex-wrap gap-x-4 gap-y-1 text-xs">
          <ReadOnlyFact label={t('quotes.form.lineQuantityHeader')}>
            <span className="tabular-nums">{formatQuoteAmount(Number(line.quantity))}</span>
            {unitSymbol ? <span className="ml-1 text-muted-foreground">{unitSymbol}</span> : null}
          </ReadOnlyFact>
          <ReadOnlyFact label={t('quotes.form.lineUnitPriceHeader')}>
            <span className="tabular-nums">{formatQuoteAmount(Number(line.unit_price))}</span>
          </ReadOnlyFact>
          <ReadOnlyFact label={t('quotes.form.lineVatRateHeader')}>
            {line.vat_rate ? line.vat_rate.name : <DetailEmpty />}
          </ReadOnlyFact>
          {line.supplier ? (
            <ReadOnlyFact label={t('quotes.form.lineSupplierHeader')}>{line.supplier.name}</ReadOnlyFact>
          ) : null}
          {variant === 'cost' ? (
            <ReadOnlyFact label={t('quotes.form.costsTab.associatedProductHeader')}>{associatedProductLabel}</ReadOnlyFact>
          ) : null}
        </dl>
        <QuoteLineAmounts
          net={Number(line.net_amount)}
          vat={Number(line.vat_amount)}
          total={Number(line.total_amount)}
        />
      </div>

      {line.additional_description ? (
        <p className={`${LINE_CARD_FOOTER_CLASS} text-xs whitespace-pre-line break-words text-muted-foreground`}>
          {line.additional_description}
        </p>
      ) : null}
    </li>
  )
}

/** The persisted rows' sums, as the editor's frame totals the rows being typed. */
function persistedTotals(lines: QuoteLine[]) {
  return lines.reduce(
    (totals, line) => ({
      net: round2(totals.net + Number(line.net_amount)),
      vat: round2(totals.vat + Number(line.vat_amount)),
      gross: round2(totals.gross + Number(line.total_amount)),
    }),
    { net: 0, vat: 0, gross: 0 },
  )
}

export interface QuoteLinesReadOnlyListProps {
  lines: QuoteLine[]
  showCommissions?: boolean
  /** Spec 0144 AC-016: `cost` adds the "Associated product" value; default `revenue` (unchanged). */
  variant?: ReadOnlyVariant
  /** Cost variant only: `offer_line_id -> label` lookup built by the caller from this SAME offer's persisted revenue rows. */
  offerLineLabelsById?: Record<number, string>
  /** Spec 0145 (D-1), revenue variant only: `offer line id -> imputed cost net` built by the caller from this SAME offer's persisted cost rows, feeds the commissions dialog's base (D-1/D-2). */
  allocatedCostNetById?: Record<number, number>
  /** Beside the frame's title: the detail's edit pencil. */
  headerAction?: ReactNode
}

/**
 * Read-only rendering of a quote's product lines, shared between the Offer
 * detail view (spec 0065) and the Contract detail view (spec 0072, BR-7): the
 * line editor's own frame and cards (user directive 2026-10-23: a saved row
 * reads like the open form), with no catalogue, edit controls or addable rows.
 */
export function QuoteLinesReadOnlyList({
  lines,
  showCommissions = false,
  variant = 'revenue',
  offerLineLabelsById,
  allocatedCostNetById,
  headerAction,
}: QuoteLinesReadOnlyListProps) {
  const { t } = useTranslation()
  const genericCostLabel = t('quotes.form.costsTab.associatedProductGeneric')

  return (
    <QuoteLineEditorFrame
      title={t(variant === 'revenue' ? 'quotes.lineEditor.rows.titleRevenue' : 'quotes.lineEditor.rows.titleCost')}
      count={lines.length}
      emptyText={t('quotes.detail.linesEmpty')}
      totals={{
        ...persistedTotals(lines),
        totalLabel: t(variant === 'revenue' ? 'quotes.lineEditor.rows.totalRevenue' : 'quotes.lineEditor.rows.totalCost'),
      }}
      catalog={null}
      headerAction={headerAction}
    >
      {lines.map((line, index) => (
        <ReadOnlyLine
          key={line.id}
          line={line}
          position={index + 1}
          showCommissions={showCommissions}
          variant={variant}
          allocatedCostNet={allocatedCostNetById?.[line.id] ?? 0}
          associatedProductLabel={
            variant === 'cost'
              ? ((line.offer_line_id != null ? offerLineLabelsById?.[line.offer_line_id] : undefined) ?? genericCostLabel)
              : undefined
          }
        />
      ))}
    </QuoteLineEditorFrame>
  )
}
