import type { Control } from 'react-hook-form'
import { useTranslation } from 'react-i18next'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { OdaLink } from '@/features/purchase-requests/oda-link'
import { LineActions } from '@/features/purchase-requests/purchase-request-line-actions'
import { CAPTIONED_CELL, LINE_GRID, WIDE_CELL } from '@/features/purchase-requests/purchase-request-line-layout'
import type { PurchaseRequestFormValues } from '@/features/purchase-requests/purchase-request-schema'
import { LineStatusBadge } from '@/features/purchase-requests/purchase-request-status-badge'
import type { PurchaseRequestLine } from '@/features/purchase-requests/types'

const EMPTY = '—'

interface PurchaseRequestLineViewProps {
  index: number
  control: Control<PurchaseRequestFormValues>
  /** The saved line, as the server returned it. */
  line: PurchaseRequestLine
  /** Present when the line edits in place (pending, or approved with the supplier still open). */
  onEdit?: () => void
  /** Present when the line may be deleted; the caller asks for confirmation. */
  onRemove?: () => void
  /** Present when the server allows a status transition. */
  onChangeStatus?: () => void
}

function Cell({ value, className, label }: { value: string; className?: string; label?: string }) {
  return (
    <div data-label={label} className={cn(CAPTIONED_CELL, 'truncate', className)} title={value}>
      {value}
    </div>
  )
}

/**
 * A saved RDA line on the detail, read-only (spec 0256 D-7): the same band as
 * the editable row (`LINE_GRID`, one header above every row), with the values
 * as text and the line's actions, the pencil first. The pencil opens the row's
 * editor in place of this band.
 */
export function PurchaseRequestLineView({ index, control, line, onEdit, onRemove, onChangeStatus }: PurchaseRequestLineViewProps) {
  const { t } = useTranslation()
  const n = index + 1
  const total = formatQuoteAmount(Number(line.total_amount))
  const breakdown = `${t('purchaseRequests.lines.taxable')} ${formatQuoteAmount(Number(line.taxable_amount))}, ${t('purchaseRequests.lines.vat')} ${formatQuoteAmount(Number(line.vat_amount))}`

  return (
    <li
      className={cn(LINE_GRID, 'rounded-md border bg-card px-2 py-1.5 text-xs')}
      aria-label={`${t('purchaseRequests.lines.line')} ${n}`}
    >
      <Cell className={WIDE_CELL} label={t('purchaseRequests.lines.product')} value={line.product?.name ?? EMPTY} />
      <Cell className={cn(WIDE_CELL, 'font-medium')} label={t('purchaseRequests.lines.description')} value={line.description} />
      <Cell className={WIDE_CELL} label={t('purchaseRequests.lines.supplier')} value={line.supplier?.name ?? EMPTY} />
      <Cell label={t('purchaseRequests.lines.unit')} value={line.unit_of_measure?.symbol ?? line.unit_of_measure?.name ?? EMPTY} />
      <Cell className="lg:text-right" label={t('purchaseRequests.lines.quantity')} value={String(Number(line.quantity))} />
      <Cell className="lg:text-right" label={t('purchaseRequests.lines.unitPrice')} value={formatQuoteAmount(Number(line.unit_price))} />
      <Cell label={t('purchaseRequests.lines.vatRate')} value={line.vat_rate?.name ?? EMPTY} />
      <div data-label={t('purchaseRequests.lines.total')} className={cn(CAPTIONED_CELL, 'lg:text-right')}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0} className="cursor-default font-medium" aria-label={`${t('purchaseRequests.lines.total')} ${n}: ${total} (${breakdown})`}>
              {total}
            </span>
          </TooltipTrigger>
          <TooltipContent>{breakdown}</TooltipContent>
        </Tooltip>
      </div>
      <div data-label={t('purchaseRequests.lines.status')} className={CAPTIONED_CELL}>
        <LineStatusBadge status={line.status} />
      </div>
      <div data-label={t('purchaseRequests.lines.oda')} className={cn(CAPTIONED_CELL, 'col-span-2 sm:col-span-1')}>
        <OdaLink purchaseOrder={line.purchase_order} odaReference={line.oda_reference} />
      </div>
      <div className="col-span-2 sm:col-span-4 lg:col-span-1">
        <LineActions
          control={control}
          index={index}
          lineId={line.id}
          locked
          hasReason={(line.reason ?? '').trim() !== ''}
          pendingFiles={0}
          showChangeStatus={onChangeStatus !== undefined}
          showRemove={onRemove !== undefined}
          onChangeStatus={() => onChangeStatus?.()}
          onExtractVat={() => undefined}
          onRemove={() => onRemove?.()}
          onEdit={onEdit}
        />
      </div>
    </li>
  )
}
