import type { ICellRendererParams } from 'ag-grid-community'
import { CELL_WRAPPER, EmptyCell } from '@/features/table/cell-renderers'
import { formatEuro } from '@/features/invoices/invoice-format'
import {
  InvoiceTagBadge,
  InvoiceTypeBadge,
  PaymentStatusIndicator,
} from '@/features/invoices/invoice-status-badges'
import {
  INVOICE_PAYMENT_STATUSES,
  INVOICE_TAGS,
  INVOICE_TYPES,
  type InvoicePaymentStatus,
  type InvoiceTag,
  type InvoiceType,
} from '@/features/invoices/types'

function isOneOf<T extends string>(options: readonly T[], value: unknown): value is T {
  return typeof value === 'string' && (options as readonly string[]).includes(value)
}

/** Money column: it-IT EUR, right-aligned, digit-aligned. */
export function MoneyCell({ value }: ICellRendererParams) {
  const formatted = formatEuro(value as string | null)
  return formatted ? (
    <span className="block w-full text-right font-medium tabular-nums text-foreground">{formatted}</span>
  ) : (
    <EmptyCell align="left" />
  )
}

export function InvoiceTypeCell({ value }: ICellRendererParams) {
  return isOneOf<InvoiceType>(INVOICE_TYPES, value) ? (
    <div className={CELL_WRAPPER}>
      <InvoiceTypeBadge type={value} />
    </div>
  ) : (
    <EmptyCell />
  )
}

export function InvoiceTagCell({ value }: ICellRendererParams) {
  return isOneOf<InvoiceTag>(INVOICE_TAGS, value) ? (
    <div className={CELL_WRAPPER}>
      <InvoiceTagBadge tag={value} />
    </div>
  ) : (
    <EmptyCell />
  )
}

/** Semaphore dot plus label of the computed payment status. */
export function PaymentStatusCell({ value }: ICellRendererParams) {
  return isOneOf<InvoicePaymentStatus>(INVOICE_PAYMENT_STATUSES, value) ? (
    <div className="flex h-full items-center overflow-hidden">
      <PaymentStatusIndicator status={value} />
    </div>
  ) : (
    <EmptyCell align="left" />
  )
}
