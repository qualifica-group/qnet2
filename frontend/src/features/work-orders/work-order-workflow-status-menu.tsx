import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuRadioGroup,
  DropdownMenuRadioItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { WorkflowStatusBadge } from '@/features/workflow-statuses/workflow-status-badge'
import { WorkflowStatusOption } from '@/features/workflow-statuses/workflow-status-option'
import { useWorkOrderWorkflowStatusChange } from '@/features/work-orders/use-work-order-workflow-status-change'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/** Field permission key of the "Stato lavorazione" (spec 0322). */
const WORKFLOW_STATUS_FIELD = 'work_order_workflow_status_id'

/** Keeps a long status name from pushing the header actions off the band. */
const BADGE_CLASS = 'max-w-28 min-w-0 sm:max-w-48'

interface WorkOrderWorkflowStatusMenuProps {
  workOrder: WorkOrderDetailWithPermissions
  onChanged?: () => void
}

/**
 * The "Stato lavorazione" (spec 0322) in the record header, beside the
 * working-phase pill (user directive 2026-11-27): its pill opens the
 * resolved set and a pick saves at once. The field permission hides it, or
 * leaves the bare pill when it is read-only.
 */
export function WorkOrderWorkflowStatusMenu({ workOrder, onChanged }: WorkOrderWorkflowStatusMenuProps) {
  const { t } = useTranslation()
  const permission = useResourcePermissions().field(WORKFLOW_STATUS_FIELD)
  const change = useWorkOrderWorkflowStatusChange(workOrder.id, onChanged)
  const current = workOrder.work_order_workflow_status
  const label = t('workOrders.detail.workflowStatus')

  if (!permission.visible) {
    return null
  }

  const badge = <WorkflowStatusBadge name={current.name} color={current.color} className={BADGE_CLASS} />
  if (permission.disabled || !permission.editable) {
    return (
      <span className="inline-flex min-w-0" aria-label={`${label}: ${current.name}`}>
        {badge}
      </span>
    )
  }

  const select = (value: string) => {
    const statusId = Number(value)
    if (statusId !== current.id) {
      change.mutate(statusId)
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <button
          type="button"
          aria-label={t('workOrders.detail.workflowStatusChange', { status: current.name })}
          disabled={change.isPending}
          className="inline-flex min-w-0 cursor-pointer items-center gap-0.5 rounded-md focus-visible:outline-2 focus-visible:outline-ring disabled:cursor-wait disabled:opacity-60"
        >
          {badge}
          <ChevronDown className="size-3.5 shrink-0 text-muted-foreground" aria-hidden="true" />
        </button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="max-w-72">
        <DropdownMenuLabel className="text-xs">{label}</DropdownMenuLabel>
        <DropdownMenuRadioGroup value={String(current.id)} onValueChange={select}>
          {workOrder.work_order_workflow_statuses.map((status) => (
            <DropdownMenuRadioItem key={status.id} value={String(status.id)} className="items-start">
              <WorkflowStatusOption
                name={status.name}
                description={status.description}
                color={status.color}
                requiresNote={false}
              />
              {status.progress_percentage !== null ? (
                <span className="ml-auto shrink-0 pl-3 text-xs text-muted-foreground tabular-nums">
                  {status.progress_percentage}%
                </span>
              ) : null}
            </DropdownMenuRadioItem>
          ))}
        </DropdownMenuRadioGroup>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
