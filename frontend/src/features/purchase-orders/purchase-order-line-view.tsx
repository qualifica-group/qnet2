import { useTranslation } from 'react-i18next'
import { Pencil, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import { cn } from '@/lib/utils'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { formatQuoteAmount } from '@/features/quotes/quote-summary'
import { CAPTIONED_CELL, LINE_GRID, WIDE_CELL } from '@/features/purchase-orders/purchase-order-line-layout'
import { IconAction } from '@/features/purchase-orders/purchase-order-line-row'
import type { PurchaseOrderLine } from '@/features/purchase-orders/types'

const EMPTY = '—'

interface PurchaseOrderLineViewProps {
  index: number
  /** The saved line, as the server returned it. */
  line: PurchaseOrderLine
  /** Present when the order is still editable by the actor. */
  onEdit?: () => void
  /** Present when the line may be deleted; the caller asks for confirmation. */
  onRemove?: () => void
}

function Cell({ value, className, label }: { value: string; className?: string; label: string }) {
  return (
    <div data-label={label} className={cn(CAPTIONED_CELL, 'truncate', className)} title={value}>
      {value}
    </div>
  )
}

/**
 * A saved ODA line on the detail, read-only (spec 0257 D-7): the same band as
 * the editable row (`LINE_GRID`, one header above every row), values as text,
 * the "RDA #id" badge of a generated line, then the pencil and the trash.
 */
export function PurchaseOrderLineView({ index, line, onEdit, onRemove }: PurchaseOrderLineViewProps) {
  const { t } = useTranslation()
  const n = index + 1
  const label = (key: string) => t(`purchaseOrderForm.lines.${key}`)
  const total = formatQuoteAmount(Number(line.total_amount))
  const breakdown = `${label('taxable')} ${formatQuoteAmount(Number(line.taxable_amount))}, ${label('vat')} ${formatQuoteAmount(Number(line.vat_amount))}`
  const requestId = line.purchase_request_line?.purchase_request_id

  return (
    <li className={cn(LINE_GRID, 'rounded-md border bg-card px-2 py-1.5 text-xs')} aria-label={`${label('line')} ${n}`}>
      <Cell className={WIDE_CELL} label={label('product')} value={line.product?.name ?? EMPTY} />
      <Cell className={cn(WIDE_CELL, 'font-medium')} label={label('description')} value={line.description} />
      <div data-label={label('costType')} className={cn(CAPTIONED_CELL, WIDE_CELL)}>
        <CostTypeBadge name={line.cost_type.name} color={line.cost_type.color} />
      </div>
      <Cell label={label('unit')} value={line.unit_of_measure?.symbol ?? line.unit_of_measure?.name ?? EMPTY} />
      <Cell className="lg:text-right" label={label('quantity')} value={String(Number(line.quantity))} />
      <Cell className="lg:text-right" label={label('unitPrice')} value={formatQuoteAmount(Number(line.unit_price))} />
      <Cell label={label('vatRate')} value={line.vat_rate?.name ?? EMPTY} />
      <div data-label={label('total')} className={cn(CAPTIONED_CELL, 'lg:text-right')}>
        <Tooltip>
          <TooltipTrigger asChild>
            <span tabIndex={0} className="cursor-default font-medium" aria-label={`${label('total')} ${n}: ${total} (${breakdown})`}>
              {total}
            </span>
          </TooltipTrigger>
          <TooltipContent>{breakdown}</TooltipContent>
        </Tooltip>
      </div>
      <Cell className={WIDE_CELL} label={label('workOrder')} value={line.work_order?.code ?? EMPTY} />
      <div className="col-span-2 flex items-center justify-end gap-1 sm:col-span-4 lg:col-span-1">
        {requestId !== undefined ? (
          <Badge variant="secondary" className="mr-auto text-[11px]" title={t('purchaseOrderForm.lines.rdaBadgeLabel', { id: requestId })}>
            {t('purchaseOrderForm.lines.rdaBadge', { id: requestId })}
          </Badge>
        ) : null}
        {onEdit ? <IconAction label={t('purchaseOrderForm.lines.edit', { n })} icon={<Pencil />} onClick={onEdit} /> : null}
        {onRemove ? (
          <IconAction label={`${label('remove')} ${n}`} icon={<Trash2 />} className="text-destructive" onClick={onRemove} />
        ) : null}
      </div>
    </li>
  )
}
