import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { StatusDescriptionHint } from '@/features/workflow-statuses/status-description-hint'
import { WorkflowStatusBadge } from '@/features/workflow-statuses/workflow-status-badge'
import { WorkflowStatusOption } from '@/features/workflow-statuses/workflow-status-option'
import { WorkflowStatusSwatch } from '@/features/workflow-statuses/workflow-status-swatch'
import type { WorkOrderFormValues } from '@/features/work-orders/use-work-order-form'
import type { WorkOrderWorkflowStatusRef } from '@/features/work-orders/types'

function ProgressHint({ percentage }: { percentage: number | null }) {
  const { t } = useTranslation()

  if (percentage === null) {
    return null
  }

  return (
    <span className="shrink-0 text-xs text-muted-foreground">
      {t('workflowStatuses.progressPercentage')}: {percentage}%
    </span>
  )
}

/** The picked status inside the closed trigger: one compact line, the description stays in the open list. */
function SelectedStatus({ status }: { status: WorkOrderWorkflowStatusRef }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={status.color} />
      <span className="truncate font-medium">{status.name}</span>
      <ProgressHint percentage={status.progress_percentage} />
    </span>
  )
}

interface WorkOrderWorkflowStatusValueProps {
  status: WorkOrderWorkflowStatusRef
}

/** Read-only value of the detail row: the status pill, its description on demand and its advancement. */
export function WorkOrderWorkflowStatusValue({ status }: WorkOrderWorkflowStatusValueProps) {
  return (
    <span className="flex min-w-0 flex-wrap items-center gap-2">
      <WorkflowStatusBadge name={status.name} color={status.color} />
      <StatusDescriptionHint description={status.description} />
      <ProgressHint percentage={status.progress_percentage} />
    </span>
  )
}

interface WorkOrderWorkflowStatusFieldProps {
  control: Control<WorkOrderFormValues>
  /** The set the backend resolved for THIS work order: offering a row outside it would only earn a 422. */
  statuses: WorkOrderWorkflowStatusRef[]
}

/**
 * "Stato lavorazione" (spec 0322) editor: a plain `Select` over the resolved
 * set, never a remote for-select. Sits in `MetaField`, so the field permission
 * hides it or turns it read-only like every other work order field.
 */
export function WorkOrderWorkflowStatusField({ control, statuses }: WorkOrderWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField
      control={control}
      name="work_order_workflow_status_id"
      metaKey="work_order_workflow_status_id"
      label={t('workOrders.detail.workflowStatus')}
    >
      {({ field, disabled }) => {
        const selected = statuses.find((status) => status.id === field.value) ?? null

        return (
          <Select
            value={field.value !== null ? String(field.value) : undefined}
            onValueChange={(next) => field.onChange(Number(next))}
            disabled={disabled}
          >
            <FormControl>
              <SelectTrigger className="h-9 w-full">
                <SelectValue placeholder={t('workOrders.detail.workflowStatusPlaceholder')}>
                  {selected ? <SelectedStatus status={selected} /> : null}
                </SelectValue>
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {statuses.map((status) => (
                <SelectItem key={status.id} value={String(status.id)}>
                  <span className="flex min-w-0 items-start justify-between gap-3">
                    <WorkflowStatusOption
                      name={status.name}
                      description={status.description}
                      color={status.color}
                      requiresNote={false}
                    />
                    <ProgressHint percentage={status.progress_percentage} />
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )
      }}
    </MetaField>
  )
}
