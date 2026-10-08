import { useTranslation } from 'react-i18next'
import { MessageSquareText } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { RecordLink } from '@/components/detail/record-link'
import { cn } from '@/lib/utils'
import { CalculationHint, type HintContent } from '@/features/work-order-contract-data/calculation-hint'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import { colorBadgeClass } from '@/features/custom-fields/color-badge-class'
import { ContractDataWarnings } from '@/features/work-order-contract-data/contract-data-warnings'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import type { ContractDataLine } from '@/features/work-order-contract-data/types'

/** The product on ONE line: code, name (truncated, full name on hover), typology badge, warning icons. */
export function ProductCell({ line }: { line: ContractDataLine }) {
  const { t } = useTranslation()
  const { code, name } = line.product

  return (
    <div className="flex min-w-0 items-center gap-1.5">
      <span className="shrink-0 font-mono text-muted-foreground">{code}</span>
      <RecordLink domain="products" id={line.product.id} className="min-w-0 font-medium">
        <span className="min-w-0 truncate" title={name}>
          {name}
        </span>
      </RecordLink>
      <ProductTypologyBadge
        name={line.typology?.name ?? t('workOrders.contractData.kind.consultancy')}
        color={line.typology?.color}
      />
      <ContractDataWarnings warnings={line.warnings} />
    </div>
  )
}

interface AmountProps {
  /** Server decimal string. */
  value: string | null
  /** The calculation behind it; without one the figure is plain. */
  hint: HintContent | null
  className?: string
}

/** A calculated figure with the explanation of its calculation; a dash when the figure is absent. */
export function Amount({ value, hint, className }: AmountProps) {
  if (value === null) {
    return <span>—</span>
  }
  return hint ? (
    <CalculationHint title={hint.title} lines={hint.lines} className={className}>
      {money(value)}
    </CalculationHint>
  ) : (
    <span className={className}>{money(value)}</span>
  )
}

/** Payment on one line: status badge (colour dot + name), "Unpaid" badge, the agreement in the hint. */
export function PaymentCell({ line }: { line: ContractDataLine }) {
  const { t } = useTranslation()
  const { status, payment_agreement: agreement, has_unpaid: hasUnpaid } = line.payment

  const badges = (
    <span className="inline-flex items-center gap-1">
      {status ? (
        <Badge variant="outline" title={status.name} className={cn('px-1.5 py-0 text-[11px]', colorBadgeClass(status.color))}>
          <PaymentStatusLabel status={status} />
        </Badge>
      ) : (
        <Badge variant="outline" className="border-border bg-muted px-1.5 py-0 text-[11px] text-muted-foreground">
          {t('workOrders.contractData.payment.noStatus')}
        </Badge>
      )}
      {hasUnpaid ? (
        <Badge variant="destructive" className="px-1.5 py-0 text-[11px]">
          {t('workOrders.contractData.payment.unpaid')}
        </Badge>
      ) : null}
    </span>
  )

  return agreement ? (
    <CalculationHint title={t('workOrders.contractData.editor.agreement')} lines={[agreement]} className="no-underline">
      {badges}
      <MessageSquareText aria-hidden="true" className="ml-1 inline size-3 text-muted-foreground" />
    </CalculationHint>
  ) : (
    badges
  )
}
