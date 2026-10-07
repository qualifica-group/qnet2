import { tintClassFor } from '@/features/custom-fields/badge-color-tokens'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { cn } from '@/lib/utils'

/**
 * Filled badge in a configured colour token, the same palette as every
 * configured status; the hairline takes the token's hue. An unset or unknown
 * token gets a neutral fill, so a badge never sits transparent on the surface
 * it stands on (ui-design.md section 1-bis).
 */
export function colorBadgeClass(color: string | null | undefined): string {
  const fill = badgeColorClass(color)
  if (!fill) {
    return 'border-border bg-muted text-foreground'
  }
  return cn(fill, tintClassFor(color)?.split(' ')[0])
}
