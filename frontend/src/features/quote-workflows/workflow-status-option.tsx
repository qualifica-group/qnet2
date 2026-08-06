import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { RequiresNoteBadge } from '@/features/quote-workflows/requires-note-badge'
import { cn } from '@/lib/utils'

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
        <span
          className={cn('size-2.5 shrink-0 rounded-full border', swatchClassFor(color) ?? 'bg-transparent')}
          aria-hidden="true"
        />
        <span className="truncate">{name}</span>
        {requiresNote ? <RequiresNoteBadge /> : null}
      </span>
      {description ? (
        <span className="line-clamp-2 text-xs text-muted-foreground">{description}</span>
      ) : null}
    </span>
  )
}
