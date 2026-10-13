import { useTranslation } from 'react-i18next'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { usePurchaseRequestLineStatusCatalog } from '@/features/purchase-request-line-statuses/use-purchase-request-line-status-catalog'
import { cn } from '@/lib/utils'
import type { LineStatus } from '@/features/purchase-requests/types'

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
 * Target status as icon buttons, in the catalog order with the configured
 * name and icon (spec 0248). Native radios (visually hidden) give the group
 * role, arrow-key navigation and focus handling for free.
 */
export function LineStatusOptions({ labelledBy, options, value, current, onValueChange }: LineStatusOptionsProps) {
  const { t } = useTranslation()
  const { statuses, byKey } = usePurchaseRequestLineStatusCatalog()
  // Catalog order first; a key the catalog does not know yet keeps its place after them.
  const ordered = [
    ...statuses.map((status) => status.systemKey).filter((key): key is LineStatus => options.includes(key as LineStatus)),
    ...options.filter((status) => !byKey.has(status)),
  ]
  return (
    <div role="radiogroup" aria-labelledby={labelledBy} className="flex flex-wrap gap-1.5">
      {ordered.map((status) => {
        const presentation = byKey.get(status)
        const label = presentation?.name ?? status
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
              <DynamicIcon name={presentation?.icon} className="size-3.5" />
              {status === current ? t('purchaseRequests.statusDialog.currentOption', { status: label }) : label}
            </span>
          </label>
        )
      })}
    </div>
  )
}
