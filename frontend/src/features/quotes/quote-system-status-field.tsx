import { useTranslation } from 'react-i18next'
import type { Control, Path } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { MetaField } from '@/features/authorization/MetaField'
import { RequiresNoteBadge } from '@/features/workflow-statuses/requires-note-badge'
import { WorkflowStatusSwatch } from '@/features/workflow-statuses/workflow-status-swatch'
import type { QuoteSystemStatusOption } from '@/features/quotes/types'

/** The two controls this field drives; a shape rather than `QuoteFormValues` so any quote-like form can mount it. */
export interface QuoteSystemStatusFormShape {
  quote_system_status_id: number | null
  note: string | null
}

interface QuoteSystemStatusControlsProps<TFieldValues extends QuoteSystemStatusFormShape> {
  control: Control<TFieldValues>
  /** The options the backend resolved for THIS quote (`QuoteResource.quote_system_statuses`, A-D7). */
  statuses: QuoteSystemStatusOption[]
  /** The system status the loaded quote currently has: an unchanged status demands no note. */
  originalStatusId: number | null
  /** The status currently picked in the form, watched by the caller so this component stays presentational. */
  selectedStatusId: number | null
}

/** The picked status as one compact line inside the closed trigger (dot, name, note marker). */
function SelectedStatus({ status }: { status: QuoteSystemStatusOption }) {
  return (
    <span className="flex min-w-0 items-center gap-2">
      <WorkflowStatusSwatch color={status.color} />
      <span className="truncate font-medium">{status.name}</span>
      {status.requires_note ? <RequiresNoteBadge /> : null}
    </span>
  )
}

/**
 * The system status select and, when demanded, its transition note. A plain
 * `Select` over the options the backend already resolved: offering a status
 * outside them would only earn a 422. The note appears only when the target
 * differs from the status the quote has AND its destination working status
 * requires one; the `superRefine` in `buildUpdateQuoteSchema` mirrors this and
 * is what blocks the submit.
 */
export function QuoteSystemStatusControls<TFieldValues extends QuoteSystemStatusFormShape>({
  control,
  statuses,
  originalStatusId,
  selectedStatusId,
}: QuoteSystemStatusControlsProps<TFieldValues>) {
  const { t } = useTranslation()
  // The shape guarantees both keys on every accepted form, but TS cannot
  // narrow a literal to `Path<TFieldValues>` through the generic.
  const statusField = 'quote_system_status_id' as Path<TFieldValues>
  const noteField = 'note' as Path<TFieldValues>

  if (statuses.length === 0) {
    return null
  }

  const selected = statuses.find((status) => status.id === selectedStatusId) ?? null
  const noteRequired = selected !== null && selected.requires_note && selected.id !== originalStatusId

  return (
    <>
      <MetaField
        control={control}
        name={statusField}
        metaKey={statusField}
        label={t('quotes.form.systemStatus')}
        hint={t('quotes.form.systemStatusHint')}
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
                  <span className="flex min-w-0 items-center gap-2">
                    <WorkflowStatusSwatch color={status.color} />
                    <span className="truncate">{status.name}</span>
                    {status.requires_note ? <RequiresNoteBadge /> : null}
                  </span>
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      {noteRequired ? (
        <MetaField
          control={control}
          name={noteField}
          metaKey={statusField}
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
    </>
  )
}
