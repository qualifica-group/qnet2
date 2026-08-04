import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import { useWatch, type Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { WorkflowStatusOption } from '@/features/opportunity-workflows/workflow-status-option'
import { RequiresNoteBadge } from '@/features/opportunity-workflows/requires-note-badge'
import { WorkflowStatusSwatch } from '@/features/request-management/request-workflow-status-field'
import type { RequestCreateFormValues } from '@/features/request-management/request-create-schema'
import type { RequestWorkflowStatusRef } from '@/features/request-management/types'

interface RequestCreateWorkflowStatusFieldProps {
  control: Control<RequestCreateFormValues>
  /** The set resolved for the criteria typed so far (`POST /request-management/form-context`): the select never offers a value outside it. */
  statuses: RequestWorkflowStatusRef[]
}

/**
 * The working status at creation (user directive 2026-07-31): the same select
 * the work panel carries, limited to the same resolved set — a request that is
 * already past "aperta" when it is opened records that straight away.
 *
 * Seeded with the set's FIRST status as soon as the criteria resolve one (user
 * directive 2026-08-04, see `useRequestCreateForm`): the create form therefore
 * submits an explicit status instead of letting OpportunityWorkflowResolver
 * derive it, which it still does for a payload that omits the key.
 *
 * Twin of `RequestWorkflowStatusField` (plain `FormField` instead of
 * `MetaField`) for the same reason as every other `RequestCreate*` section:
 * this create-only form has no `permissions` envelope to gate against.
 *
 * With no categoria prodotto picked the criteria resolve no set: the section
 * stays in place but the select is DISABLED (user directive 2026-08-04) — the
 * field keeps its slot in the layout and says why it cannot be used yet,
 * instead of appearing out of nowhere once a category is chosen.
 */
export function RequestCreateWorkflowStatusField({
  control,
  statuses,
}: RequestCreateWorkflowStatusFieldProps) {
  const { t } = useTranslation()
  const selectedStatusId = useWatch({ control, name: 'opportunity_workflow_status_id' })

  const hasStatuses = statuses.length > 0
  const selected = statuses.find((status) => status.id === selectedStatusId) ?? null

  return (
    <FormSection
      icon={Workflow}
      title={t('requestManagement.workPanel.workflowStatus.title', { defaultValue: 'Working status' })}
      description={t('requestManagement.workPanel.workflowStatus.sectionDescription', {
        defaultValue: 'Advance the working state of the request.',
      })}
    >
      <FormField
        control={control}
        name="opportunity_workflow_status_id"
        render={({ field }) => (
          <FormItem>
            <FormLabel>
              {t('requestManagement.workPanel.workflowStatus.label', { defaultValue: 'Working status' })}
            </FormLabel>
            <Select
              value={field.value !== null ? String(field.value) : undefined}
              onValueChange={(next) => field.onChange(Number(next))}
              disabled={!hasStatuses}
            >
              <FormControl>
                <SelectTrigger className="h-9 w-full">
                  <SelectValue
                    placeholder={
                      hasStatuses
                        ? t('requestManagement.workPanel.workflowStatus.placeholder', {
                            defaultValue: 'Select a status',
                          })
                        : t('requestManagement.workPanel.workflowStatus.awaitingCriteria', {
                            defaultValue: 'Pick a product category first',
                          })
                    }
                  >
                    {selected ? (
                      <span className="flex min-w-0 items-center gap-2">
                        <WorkflowStatusSwatch color={selected.color} />
                        <span className="truncate font-medium">{selected.name}</span>
                        {selected.requires_note ? <RequiresNoteBadge /> : null}
                      </span>
                    ) : null}
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

            {selected?.description ? (
              <p className="text-xs text-muted-foreground">{selected.description}</p>
            ) : null}

            <FormMessage />
          </FormItem>
        )}
      />

      {selected?.requires_note ? (
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
