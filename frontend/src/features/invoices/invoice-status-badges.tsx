import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { BADGE_BASE, BADGE_COLOR_CLASSES } from '@/features/table/cell-renderers'
import type {
  InstallmentStatus,
  InvoicePaymentStatus,
  InvoiceTag,
  InvoiceType,
} from '@/features/invoices/types'

/** Semaphore dot per payment status, design tokens only (theme-safe). */
const PAYMENT_STATUS_DOT: Record<InvoicePaymentStatus, string> = {
  paid: 'bg-success',
  not_due: 'bg-primary',
  overdue: 'bg-warning',
  seriously_overdue: 'bg-destructive',
}

const TYPE_COLORS: Record<InvoiceType, string> = {
  proforma: BADGE_COLOR_CLASSES.blue,
  invoice: BADGE_COLOR_CLASSES.green,
}

const TAG_COLORS: Record<InvoiceTag, string> = {
  estimate: BADGE_COLOR_CLASSES.amber,
  final: BADGE_COLOR_CLASSES.teal,
}

const INSTALLMENT_COLORS: Record<InstallmentStatus, string> = {
  unpaid: BADGE_COLOR_CLASSES.slate,
  partially_paid: BADGE_COLOR_CLASSES.amber,
  paid: BADGE_COLOR_CLASSES.green,
}

/** Colored dot plus text label: the status is never conveyed by color alone. */
export function PaymentStatusIndicator({ status }: { status: InvoicePaymentStatus }) {
  const { t } = useTranslation()
  return (
    <span className="inline-flex items-center gap-1.5 text-xs">
      <span className={cn('size-2 shrink-0 rounded-full', PAYMENT_STATUS_DOT[status])} aria-hidden="true" />
      <span className="truncate">{t(`invoices.paymentStatus.${status}`)}</span>
    </span>
  )
}

export function InvoiceTypeBadge({ type }: { type: InvoiceType }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, TYPE_COLORS[type])}>
      {t(`invoices.types.${type}`)}
    </Badge>
  )
}

export function InvoiceTagBadge({ tag }: { tag: InvoiceTag }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, TAG_COLORS[tag])}>
      {t(`invoices.tags.${tag}`)}
    </Badge>
  )
}

export function InstallmentStatusBadge({ status }: { status: InstallmentStatus }) {
  const { t } = useTranslation()
  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, INSTALLMENT_COLORS[status])}>
      {t(`invoices.detail.installmentStatus.${status}`)}
    </Badge>
  )
}
