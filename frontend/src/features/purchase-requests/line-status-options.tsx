import { useTranslation } from 'react-i18next'
import { Check, Clock, Package, PauseCircle, ShoppingCart, X, type LucideIcon } from 'lucide-react'
import { cn } from '@/lib/utils'
import type { LineStatus } from '@/features/purchase-requests/types'

const STATUS_ICONS: Record<LineStatus, LucideIcon> = {
  pending_approval: Clock,
  approved: Check,
  ordered: ShoppingCart,
  received: Package,
  rejected: X,
  on_hold: PauseCircle,
}

interface LineStatusOptionsProps {
  /** Id of the element labelling the group. */
  labelledBy: string
  options: readonly LineStatus[]
  value: LineStatus | null
  /** Status of a single selected line: tagged "(current)". */
  current: LineStatus | null
  onValueChange: (status: LineStatus) => void
}

/**
 * Target status as icon buttons. Native radios (visually hidden) give the
 * group role, arrow-key navigation and focus handling for free.
 */
export function LineStatusOptions({ labelledBy, options, value, current, onValueChange }: LineStatusOptionsProps) {
  const { t } = useTranslation()
  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-wrap gap-1.5">
      {options.map((status) => {
        const Icon = STATUS_ICONS[status]
        const label = t(`purchaseRequests.lineStatuses.${status}`)
        return (
          <label key={status} className="cursor-pointer">
            <input
              type="radio"
              name="purchase-line-status-target"
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
              {status === current ? t('purchaseRequests.statusDialog.currentOption', { status: label }) : label}
            </span>
          </label>
        )
      })}
    </div>
  )
}
