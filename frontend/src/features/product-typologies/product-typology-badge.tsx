import { Badge } from '@/components/ui/badge'
import { colorBadgeClass } from '@/features/custom-fields/color-badge-class'
import { cn } from '@/lib/utils'

interface ProductTypologyBadgeProps {
  name: string
  /** Colour token of the typology (`BADGE_COLOR_TOKENS`); neutral when unset or unknown. */
  color?: string | null
  className?: string
}

/** The product typology as a compact badge, on one line with the product it labels. */
export function ProductTypologyBadge({ name, color, className }: ProductTypologyBadgeProps) {
  return (
    <Badge variant="outline" className={cn('px-1.5 py-0 text-[11px]', colorBadgeClass(color), className)}>
      {name}
    </Badge>
  )
}
