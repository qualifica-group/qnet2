import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'
import type { ContractPaymentStatusRef } from '@/features/work-order-contract-data/types'

/** A payment status as colour dot + name: the name always carries the meaning, the dot only decorates. */
export function PaymentStatusLabel({ status }: { status: Pick<ContractPaymentStatusRef, 'name' | 'color'> }) {
  return (
    <span className="inline-flex min-w-0 items-center gap-1.5">
      <span
        aria-hidden="true"
        className={cn('size-2.5 shrink-0 rounded-full', swatchClassFor(status.color) ?? 'bg-muted-foreground')}
      />
      <span className="truncate">{status.name}</span>
    </span>
  )
}
