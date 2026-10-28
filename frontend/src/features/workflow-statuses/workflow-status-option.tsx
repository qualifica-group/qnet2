import { RequiresNoteBadge } from '@/features/workflow-statuses/requires-note-badge'
import { WorkflowStatusSwatch } from '@/features/workflow-statuses/workflow-status-swatch'

interface WorkflowStatusOptionProps {
  name: string
  description: string | null
  color: string | null
  requiresNote: boolean
}

/**
 * One working-status option as rendered inside a `<SelectItem>` — the single
 * place the status' color dot, its `description` and the "note required"
 * marker are laid out, shared by the opportunity form and the
 * request-management work form (spec 0047 amendment) so the two pickers can
 * never drift.
 */
export function WorkflowStatusOption({ name, description, color, requiresNote }: WorkflowStatusOptionProps) {
  return (
    <span className="flex min-w-0 flex-col gap-0.5">
      <span className="flex items-center gap-2">
        <WorkflowStatusSwatch color={color} />
        <span className="truncate">{name}</span>
        {requiresNote ? <RequiresNoteBadge /> : null}
      </span>
      {description ? (
        <span className="line-clamp-2 text-xs text-muted-foreground">{description}</span>
      ) : null}
    </span>
  )
}
