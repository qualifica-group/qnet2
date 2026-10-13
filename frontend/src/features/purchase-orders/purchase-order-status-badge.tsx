import { Badge } from '@/components/ui/badge'
import { cn } from '@/lib/utils'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'

/**
 * Order status as a badge drawn from the status catalog (spec 0248): the name,
 * color and icon are the ones configured by the user, the label always
 * travels with them (never colour alone). While the catalog loads, or for a
 * value it lacks, a neutral badge shows the raw enum value (as the RDA line
 * badge does).
 */
export function PurchaseOrderStatusBadge({ status, className }: { status: PurchaseOrderStatus; className?: string }) {
  const { byKey } = usePurchaseOrderStatusCatalog()
  const entry = byKey.get(status)

  if (!entry) {
    return (
      <Badge variant="outline" className={cn(BADGE_BASE, 'text-[11px]', className)}>
        {status}
      </Badge>
    )
  }

  // Without a configured icon the token's strong dot marks the state instead.
  const dotClass = entry.icon ? undefined : swatchClassFor(entry.color)

  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5 text-[11px]', badgeColorClass(entry.color), className)}>
      <DynamicIcon name={entry.icon} className="size-3.5 shrink-0" />
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{entry.name}</span>
    </Badge>
  )
}
