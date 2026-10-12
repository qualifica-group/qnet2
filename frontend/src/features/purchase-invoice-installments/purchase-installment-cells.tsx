import { useTranslation } from 'react-i18next'
import type { ICellRendererParams } from 'ag-grid-community'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES, CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import {
  PURCHASE_INSTALLMENT_STATUSES,
  PURCHASE_INVOICE_TYPES,
  type PurchaseInstallmentStatus,
  type PurchaseInvoiceType,
} from '@/features/purchase-invoice-installments/types'

const OVERDUE_YES = 'yes'

const STATUS_COLORS: Record<PurchaseInstallmentStatus, string> = {
  unpaid: BADGE_COLOR_CLASSES.slate,
  paid: BADGE_COLOR_CLASSES.green,
}

const TYPE_COLORS: Record<PurchaseInvoiceType, string> = {
  proforma: BADGE_COLOR_CLASSES.blue,
  invoice: BADGE_COLOR_CLASSES.green,
}

function isOneOf<T extends string>(options: readonly T[], value: unknown): value is T {
  return typeof value === 'string' && (options as readonly string[]).includes(value)
}

/** Derived installment status (unpaid / paid) with the payable-side labels. */
export function PurchaseStatusCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  return isOneOf<PurchaseInstallmentStatus>(PURCHASE_INSTALLMENT_STATUSES, value) ? (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, STATUS_COLORS[value])}>
        {t(`enums.purchase_installment_status.${value}`)}
      </Badge>
    </div>
  ) : (
    <EmptyCell />
  )
}

/** Document kind (proforma / invoice) as a badge. */
export function PurchaseDocumentTypeCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  return isOneOf<PurchaseInvoiceType>(PURCHASE_INVOICE_TYPES, value) ? (
    <div className={CELL_WRAPPER}>
      <Badge variant="secondary" className={cn(BADGE_BASE, TYPE_COLORS[value])}>
        {t(`enums.purchase_invoice_type.${value}`)}
      </Badge>
    </div>
  ) : (
    <EmptyCell />
  )
}

/** Derived overdue flag as a Yes / No badge (labels shared with the Set Filter checklist). */
export function PurchaseOverdueCell({ value }: ICellRendererParams) {
  const { t } = useTranslation()
  const isOverdue = value === OVERDUE_YES || value === true
  return (
    <div className={CELL_WRAPPER}>
      <Badge
        variant="secondary"
        className={cn(BADGE_BASE, isOverdue ? BADGE_COLOR_CLASSES.red : BADGE_COLOR_CLASSES.slate)}
      >
        {t(`enums.installment_overdue.${isOverdue ? 'yes' : 'no'}`)}
      </Badge>
    </div>
  )
}

/** Days past due, right-aligned; zero (not overdue) renders empty. */
export function PurchaseDaysOverdueCell({ value }: ICellRendererParams) {
  const days = Number(value)
  return Number.isFinite(days) && days > 0 ? (
    <span className="block w-full text-right font-medium tabular-nums text-destructive">{days}</span>
  ) : (
    <EmptyCell align="left" />
  )
}
