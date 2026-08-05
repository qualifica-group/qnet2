import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import { useFormState, useWatch } from 'react-hook-form'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { RequiresNoteBadge } from '@/features/opportunity-workflows/requires-note-badge'
import { WorkflowStatusOption } from '@/features/opportunity-workflows/workflow-status-option'
import { WorkflowStatusSwatch } from '@/features/opportunity-workflows/workflow-status-swatch'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

interface RequestWorkflowStatusFieldProps {
  control: Control<RequestWorkFormValues>
  /** The resolved working-state set for this opportunity (spec 0047/0049 AC-063): the select never offers a value outside it. */
  statuses: RequestWorkflowStatusRef[]
}

/**
 * The selected status as rendered INSIDE the closed trigger: one compact line
 * (dot, name, note marker). Deliberately not `WorkflowStatusOption` — that one
 * also stacks the `description`, which turns the trigger into a two/three-line
 * block. The description belongs to the open dropdown, where it helps choose.
 */
function SelectedStatus({ status }: { status: RequestWorkflowStatusRef }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={status.color} />
      <span className="truncate font-medium">{status.name}</span>
      {status.requires_note ? <RequiresNoteBadge /> : null}
    </span>
  )
}

/**
 * Spec 0049 (AC-063): the operator's only lever over the working state
 * (`opportunity_workflow_status_id`) — a `Select` limited to the
 * `workflow_statuses` set the backend already resolved for this opportunity,
 * so an out-of-set value is unreachable from the UI (transitions stay free
 * within the set, spec 0047). Renders nothing when the resolved set is empty
 * (defensive only: the backend always seeds at least the system rows).
 *
 * Presented as its own section, like every other block of the work panel: the
 * closed trigger stays a single compact line (user directive 2026-08-04 — the
 * selected status' `description` is NOT repeated under the control, it only
 * reads inside the open dropdown, same as the create form).
 */
export function RequestWorkflowStatusField({ control, statuses }: RequestWorkflowStatusFieldProps) {
  const { t } = useTranslation()
  const selectedStatusId = useWatch({ control, name: 'opportunity_workflow_status_id' })
  // RHF's own dirty tracking against the loaded `defaultValues` (spec 0054
  // D-5): "changed" means differing from what the panel loaded, exactly the
  // server's definition — no separate "original status" prop to thread
  // through from the caller.
  const { dirtyFields } = useFormState({ control, name: 'opportunity_workflow_status_id' })

  if (statuses.length === 0) {
    return null
  }

  const selected = statuses.find((status) => status.id === selectedStatusId) ?? null
  const noteRequired = Boolean(dirtyFields.opportunity_workflow_status_id) && Boolean(selected?.requires_note)

  return (
    <FormSection
      icon={Workflow}
      title={t('requestManagement.workPanel.workflowStatus.title', { defaultValue: 'Working status' })}
      description={t('requestManagement.workPanel.workflowStatus.sectionDescription', {
        defaultValue: 'Advance the working state of the request.',
      })}
    >
      <MetaField
        control={control}
        name="opportunity_workflow_status_id"
        metaKey="opportunity_workflow_status_id"
        label={t('requestManagement.workPanel.workflowStatus.label', { defaultValue: 'Working status' })}
      >
        {({ field, disabled }) => (
          <Select
            value={field.value !== null ? String(field.value) : undefined}
            onValueChange={(next) => field.onChange(Number(next))}
            disabled={disabled}
          >
            <FormControl>
              <SelectTrigger className="h-9 w-full">
                <SelectValue
                  placeholder={t('requestManagement.workPanel.workflowStatus.placeholder', {
                    defaultValue: 'Select a status',
                  })}
                >
                  {selected ? <SelectedStatus status={selected} /> : null}
                </SelectValue>
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {statuses.map((status) => (
                <SelectItem key={status.id} value={String(status.id)}>
                  <WorkflowStatusOption
                    name={status.name}
                    description={status.description}
                    color={status.color}
                    requiresNote={status.requires_note}
                  />
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      {noteRequired ? (
        <FormField
          control={control}
          name="note"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>
                {t('requestManagement.workPanel.workflowStatus.noteLabel', { defaultValue: 'Note' })}
              </FormLabel>
              <FormControl>
                <Textarea
                  {...field}
                  rows={2}
                  className="text-sm"
                  placeholder={t('requestManagement.workPanel.workflowStatus.notePlaceholder', {
                    defaultValue: 'Explain the reason for this change…',
                  })}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
      ) : null}
    </FormSection>
  )
}
