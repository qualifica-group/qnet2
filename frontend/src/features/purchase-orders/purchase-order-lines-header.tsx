import { useTranslation } from 'react-i18next'
import { LINE_HEADER_GRID } from '@/features/purchase-orders/purchase-order-line-layout'

const HEADER_KEYS = [
  'product',
  'description',
  'costType',
  'unit',
  'quantity',
  'unitPrice',
  'vatRate',
  'total',
  'workOrder',
] as const

const RIGHT_ALIGNED: readonly string[] = ['quantity', 'unitPrice', 'total']

/**
 * The single column header above the lines (desktop only). Hidden from
 * assistive tech: every field already carries its own named label.
 */
export function PurchaseOrderLinesHeader() {
  const { t } = useTranslation()
  return (
    <div className={LINE_HEADER_GRID} aria-hidden="true">
      {HEADER_KEYS.map((key) => (
        <span key={key} className={RIGHT_ALIGNED.includes(key) ? 'truncate text-right' : 'truncate'}>
          {t(`purchaseOrderForm.lines.${key}`)}
        </span>
      ))}
      <span />
    </div>
  )
}
