import { Badge } from '@/components/ui/badge'
import { BADGE_BASE, badgeColorClass } from '@/features/table/cell-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'

interface WorkflowStatusBadgeProps {
  name: string
  color: string | null
  className?: string
}

/**
 * A working status as a colored pill, for the surfaces that are NOT a grid
 * cell (record headers, read-only summaries) — the grid keeps `StatusBadgeCell`.
 * Presentational and domain-type free, so the Offerta and the Gestione Richieste
 * panels can both wear it without importing each other's types; the color
 * vocabulary is the shared one every other status pill uses.
 */
export function WorkflowStatusBadge({ name, color, className }: WorkflowStatusBadgeProps) {
  const dotClass = swatchClassFor(color)

  return (
    <Badge variant="secondary" className={cn(BADGE_BASE, 'gap-1.5', badgeColorClass(color), className)}>
      {dotClass ? <span className={cn('size-1.5 shrink-0 rounded-full', dotClass)} aria-hidden="true" /> : null}
      <span className="truncate">{name}</span>
    </Badge>
  )
}
