import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { RequiresNoteBadge } from '@/features/opportunity-workflows/requires-note-badge'
import { WorkflowStatusOption } from '@/features/opportunity-workflows/workflow-status-option'
import { WorkflowStatusSwatch } from '@/features/opportunity-workflows/workflow-status-swatch'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'
import type { OpportunityWorkflowStatusRef } from '@/features/opportunities/types'

interface OpportunityWorkflowStatusFieldProps {
  control: Control<OpportunityFormValues>
  /**
   * The resolved working-state set for THIS opportunity (spec 0047,
   * `OpportunityResource.workflow_statuses`). `null` = unknown yet (create
   * mode: the set depends on server-resolved criteria, only known once the
   * opportunity is saved) — nothing is rendered. An empty array degrades the
   * same way (defensive only: the backend always seeds at least the
   * 'open'/'closed_won'/'closed_lost' system rows).
   */
  statuses: OpportunityWorkflowStatusRef[] | null
  className?: string
}

/**
 * The selected status as rendered INSIDE the closed trigger: one compact line
 * (dot, name, note marker). Deliberately not `WorkflowStatusOption` — that one
 * also stacks the `description`, which turns the trigger into a two/three-line
 * block. The description belongs to the open dropdown, where it helps choose.
 * Identical to the Gestione Richieste panel's own trigger.
 */
function SelectedStatus({ status }: { status: OpportunityWorkflowStatusRef }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={status.color} />
      <span className="truncate font-medium">{status.name}</span>
      {status.requires_note ? <RequiresNoteBadge /> : null}
    </span>
  )
}

/**
 * Spec 0047 (AC-026): the manual override of the resolved "stato di
 * lavorazione", limited to the set the backend already resolved for this
 * opportunity — never a remote for-select, a plain `Select` over `statuses`.
 *
 * Rendered as Gestione Richieste renders it (user directive 2026-08-05): its
 * OWN card, and a closed trigger that is one compact line (swatch, name, note
 * marker) instead of a bare value. It is the card's only content — the
 * read-only computed status was dropped from the form (same directive): the
 * identity bar already carries it, merged exactly as the table merges it.
 *
 * Edit mode only: on create the set is not yet known (it depends on
 * server-resolved criteria), so the caller passes `statuses={null}` and this
 * renders nothing.
 */
export function OpportunityWorkflowStatusField({
  control,
  statuses,
  className,
}: OpportunityWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  if (!statuses || statuses.length === 0) {
    return null
  }

  return (
    <FormSection
      icon={Workflow}
      title={t('opportunities.form.sections.workflowStatus.title')}
      description={t('opportunities.form.sections.workflowStatus.description')}
      className={className}
    >
      <MetaField
        control={control}
        name="opportunity_workflow_status_id"
        metaKey="opportunity_workflow_status_id"
        label={t('opportunities.form.workflowStatus')}
        hint={t('opportunities.form.workflowStatusHint')}
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
                  <SelectValue placeholder={t('opportunities.form.selectPlaceholder')}>
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
          )
        }}
      </MetaField>
    </FormSection>
  )
}
