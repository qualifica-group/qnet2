import { useTranslation } from 'react-i18next'
import { Workflow } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { RequiresNoteBadge } from '@/features/quote-workflows/requires-note-badge'
import { WorkflowStatusOption } from '@/features/quote-workflows/workflow-status-option'
import { WorkflowStatusSwatch } from '@/features/quote-workflows/workflow-status-swatch'
import type { QuoteFormValues } from '@/features/quotes/quote-schema'
import type { QuoteWorkflowStatusRef } from '@/features/quotes/types'

interface QuoteWorkflowStatusFieldProps {
  control: Control<QuoteFormValues>
  /**
   * The set the backend resolved for THIS quote
   * (`QuoteResource.quote_workflow_statuses`, spec 0083 AC-050). `null` = not
   * known yet (create mode: the set depends on criteria the server resolves
   * from the quote's own offer lines) — nothing is rendered and the backend
   * assigns the `open` row on save (AC-020).
   */
  statuses: QuoteWorkflowStatusRef[] | null
  /** The status the loaded quote currently holds; `null` in create mode. Drives whether the note is demanded. */
  originalStatusId: number | null
  /** The status currently picked in the form, watched by the caller so this component stays presentational. */
  selectedStatusId: number | null
  className?: string
}

/**
 * The picked status as rendered INSIDE the closed trigger: one compact line
 * (dot, name, note marker). Deliberately not `WorkflowStatusOption`, which also
 * stacks the `description` and would turn the trigger into a multi-line block —
 * the description belongs to the open dropdown, where it helps choose.
 */
function SelectedStatus({ status }: { status: QuoteWorkflowStatusRef }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={status.color} />
      <span className="truncate font-medium">{status.name}</span>
      {status.requires_note ? <RequiresNoteBadge /> : null}
    </span>
  )
}

/**
 * Operational status of the quote (spec 0083): a plain `Select` over the set
 * the backend already resolved, never a remote for-select — offering a row
 * outside that set would only earn a 422 (AC-021).
 *
 * The transition note appears only when it is actually demanded: the target
 * row differs from the one the quote holds AND carries `requires_note`
 * (AC-023). An unchanged status demands nothing (AC-026), so re-saving a quote
 * already parked on such a row never asks again. The field is mirrored by the
 * `superRefine` in `buildUpdateQuoteSchema`, which is what blocks the submit —
 * this component only decides visibility.
 */
export function QuoteWorkflowStatusField({
  control,
  statuses,
  originalStatusId,
  selectedStatusId,
  className,
}: QuoteWorkflowStatusFieldProps) {
  const { t } = useTranslation()

  if (!statuses || statuses.length === 0) {
    return null
  }

  const selected = statuses.find((status) => status.id === selectedStatusId) ?? null
  const noteRequired =
    selected !== null && selected.requires_note && selected.id !== originalStatusId

  return (
    <FormSection
      icon={Workflow}
      title={t('quotes.form.sections.workflowStatus.title')}
      description={t('quotes.form.sections.workflowStatus.description')}
      className={className}
    >
      <MetaField
        control={control}
        name="quote_workflow_status_id"
        metaKey="quote_workflow_status_id"
        label={t('quotes.form.workflowStatus')}
        hint={t('quotes.form.workflowStatusHint')}
      >
        {({ field, disabled }) => (
          <Select
            value={field.value !== null ? String(field.value) : undefined}
            onValueChange={(next) => field.onChange(Number(next))}
            disabled={disabled}
          >
            <FormControl>
              <SelectTrigger className="h-9 w-full">
                <SelectValue placeholder={t('quotes.form.selectPlaceholder')}>
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
        <MetaField
          control={control}
          name="note"
          metaKey="quote_workflow_status_id"
          label={t('quotes.form.note')}
          hint={t('quotes.form.noteHint')}
        >
          {({ field, disabled }) => (
            <FormControl>
              <Textarea
                {...field}
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value)}
                disabled={disabled}
                rows={3}
                placeholder={t('quotes.form.notePlaceholder')}
              />
            </FormControl>
          )}
        </MetaField>
      ) : null}
    </FormSection>
  )
}
