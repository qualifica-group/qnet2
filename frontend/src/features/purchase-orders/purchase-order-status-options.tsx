import { cn } from '@/lib/utils'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { usePurchaseOrderStatusCatalog } from '@/features/purchase-order-statuses/use-purchase-order-status-catalog'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'

interface PurchaseOrderStatusOptionsProps {
  /** Id of the element labelling the group. */
  labelledBy: string
  /** Only the statuses the server allows from here (`abilities.transitions`). */
  options: readonly PurchaseOrderStatus[]
  value: PurchaseOrderStatus | null
  onValueChange: (status: PurchaseOrderStatus) => void
}

/**
 * Target status as icon buttons (name and icon from the catalog, in catalog
 * order); native radios (visually hidden) give group role and arrow-key navigation.
 */
export function PurchaseOrderStatusOptions({ labelledBy, options, value, onValueChange }: PurchaseOrderStatusOptionsProps) {
  const { entries, byKey } = usePurchaseOrderStatusCatalog()
  // Shown in the catalog order, which the user can rearrange.
  const ordered = [...options].sort(
    (a, b) => entries.findIndex((entry) => entry.systemKey === a) - entries.findIndex((entry) => entry.systemKey === b),
  )

  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-wrap gap-1.5">
      {ordered.map((status) => {
        const entry = byKey.get(status)
        return (
          <label key={status} className="cursor-pointer">
            <input
              type="radio"
              name="purchase-order-status-target"
              className="peer sr-only"
              checked={value === status}
              onChange={() => onValueChange(status)}
            />
            <span
              className={cn(
                'inline-flex items-center gap-1.5 rounded-md border border-field-border bg-card px-2.5 py-1 text-xs font-medium text-muted-foreground transition-colors',
                'hover:text-foreground peer-focus-visible:ring-[3px] peer-focus-visible:ring-ring/50',
                'peer-checked:border-primary peer-checked:bg-primary peer-checked:text-primary-foreground',
              )}
            >
              <DynamicIcon name={entry?.icon} className="size-3.5" />
              {entry?.name ?? status}
            </span>
          </label>
        )
      })}
    </div>
  )
}
