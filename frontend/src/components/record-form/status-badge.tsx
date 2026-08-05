import type { ReactNode } from 'react'
import { Badge } from '@/components/ui/badge'
import { WorkflowStatusSwatch } from '@/features/opportunity-workflows/workflow-status-swatch'

/**
 * A compact status pill for the record forms' identity bar: micro-label +
 * swatch + name, so state never reads from color alone. Shared, so the pill a
 * form shows for the status being CHOSEN is the same one another form shows
 * for the status it has persisted.
 */
export function StatusBadge({ label, color, children }: { label: string; color: string | null; children: ReactNode }) {
  return (
    <Badge variant="secondary" className="h-5 min-h-5 max-w-full gap-1.5">
      <span className="text-muted-foreground">{label}</span>
      <WorkflowStatusSwatch color={color} />
      <span className="truncate">{children}</span>
    </Badge>
  )
}
