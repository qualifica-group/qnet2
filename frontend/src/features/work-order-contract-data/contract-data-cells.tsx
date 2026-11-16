import { useTranslation } from 'react-i18next'
import { BookUser } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { RecordLink } from '@/components/detail/record-link'
import { cn } from '@/lib/utils'
import { CalculationHint, type HintContent } from '@/components/calculation-hint'
import { money } from '@/features/work-order-contract-data/contract-data-format'
import { colorBadgeClass } from '@/features/custom-fields/color-badge-class'
import { ContractDataWarnings } from '@/features/work-order-contract-data/contract-data-warnings'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'
import { PaymentStatusLabel } from '@/features/work-order-contract-data/payment-status-label'
import type { ContractDataLine, ContractPaymentStatusRef } from '@/features/work-order-contract-data/types'

interface ProductCellProps {
  line: ContractDataLine
  /** Multi-offer work order (spec 0267): the client of the line's own offer goes under the product. */
  showRegistry: boolean
}

/**
 * The product on ONE line: code, name (truncated, full name on hover), typology
 * badge, warning icons; on a multi-offer work order, the line's client below.
 */
export function ProductCell({ line, showRegistry }: ProductCellProps) {
  const { t } = useTranslation()
  const { code, name } = line.product

  return (
    <div className="flex min-w-0 flex-col gap-0.5">
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
      {showRegistry && line.registry ? (
        <span className="flex min-w-0 items-center gap-1 text-[11px]">
          <BookUser className="size-3 shrink-0 text-muted-foreground" aria-hidden="true" />
          <RecordLink domain="registries" id={line.registry.id} className="min-w-0 text-primary">
            {line.registry.name}
          </RecordLink>
        </span>
      ) : null}
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

/** The payment status of a line: its colour and name, or "No status". */
export function PaymentStatusBadge({ status }: { status: ContractPaymentStatusRef | null }) {
  const { t } = useTranslation()

  return status ? (
    <Badge variant="outline" title={status.name} className={cn('max-w-full min-w-0 px-1.5 py-0 text-xs', colorBadgeClass(status.color))}>
      <PaymentStatusLabel status={status} />
    </Badge>
  ) : (
    <Badge variant="outline" className="border-border bg-muted px-1.5 py-0 text-xs text-muted-foreground">
      {t('workOrders.contractData.payment.noStatus')}
    </Badge>
  )
}
