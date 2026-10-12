import { useTranslation } from 'react-i18next'
import { Ban, Check, CheckCheck, Clock, FilePen, PackageCheck, Send, type LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { PurchaseOrderStatus } from '@/features/purchase-orders/types'

const STATUS_ICONS: Record<PurchaseOrderStatus, LucideIcon> = {
  draft: FilePen,
  in_validation: Clock,
  approved: Check,
  sent_to_supplier: Send,
  partially_fulfilled: PackageCheck,
  fulfilled: CheckCheck,
  cancelled: Ban,
}

interface PurchaseOrderStatusOptionsProps {
  /** Id of the element labelling the group. */
  labelledBy: string
  /** Only the statuses the server allows from here (`abilities.transitions`). */
  options: readonly PurchaseOrderStatus[]
  value: PurchaseOrderStatus | null
  onValueChange: (status: PurchaseOrderStatus) => void
}

/** Target status as icon buttons; native radios (visually hidden) give group role and arrow-key navigation. */
export function PurchaseOrderStatusOptions({ labelledBy, options, value, onValueChange }: PurchaseOrderStatusOptionsProps) {
  const { t } = useTranslation()
  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-wrap gap-1.5">
      {options.map((status) => {
        const Icon = STATUS_ICONS[status]
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
              <Icon className="size-3.5" aria-hidden="true" />
              {t(`purchaseOrders.statuses.${status}`)}
            </span>
          </label>
        )
      })}
    </div>
  )
}
