import { Badge } from '@/components/ui/badge'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'

interface CostTypeBadgeProps {
  name: string
  /** A `BADGE_COLOR_TOKENS` token; absent or unknown falls back to the neutral badge. */
  color?: string | null
  className?: string
}

/** A cost type as a compact pill: the token's color with its strong-shade dot (spec 0249 D-6). */
export function CostTypeBadge({ name, color, className }: CostTypeBadgeProps) {
  const dotClass = swatchClassFor(color)

  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(color), className)}>
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{name}</span>
    </Badge>
  )
}
