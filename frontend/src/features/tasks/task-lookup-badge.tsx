import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import type { TaskLookupRef } from '@/features/tasks/types'

interface TaskLookupBadgeProps {
  /** The configured lookup row (status/type/priority/importance/category); `null` renders nothing. */
  value: TaskLookupRef | null | undefined
  className?: string
}

/**
 * One configured lookup rendered as a compact pill with its own token color,
 * icon and label (AC-072): changing the color or the icon from the
 * configurator changes this badge with no code change, because both come from
 * the row. `color` is a `BADGE_COLOR_TOKENS` token mapped through the SINGLE
 * seam every colored badge in the app goes through (`badgeColorClass`), never
 * a hex. Falls back to a solid status dot when the row carries no icon, so
 * the pill still reads as a state indicator.
 */
export function TaskLookupBadge({ value, className }: TaskLookupBadgeProps) {
  if (!value) {
    return null
  }

  const dotClass = value.icon ? undefined : swatchClassFor(value.color)

  return (
    <Badge
      variant="secondary"
      className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(value.color), className)}
    >
      <DynamicIcon name={value.icon} className="size-3.5 shrink-0" />
      {dotClass ? (
        <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" />
      ) : null}
      <span className="truncate">{value.name}</span>
    </Badge>
  )
}
