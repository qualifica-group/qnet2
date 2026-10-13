import { get, type FieldErrors } from 'react-hook-form'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'

type FormField = keyof TimeEntryFormValues

/**
 * The form field keying each row (spec 0226 D-2): its single open editor and
 * the error its closed row shows. The grouped rows key on their required or
 * leading field.
 */
export const TIME_ENTRY_ROW_FIELD = {
  title: 'title',
  date: 'date',
  type: 'task_type_id',
  schedule: 'minutes',
  context: 'registry_id',
  notes: 'notes',
} as const satisfies Record<string, FormField>

/** Every form field a row's editor shows, keyed by the row's own field. */
const ROW_EDITOR_FIELDS: Record<string, readonly FormField[]> = {
  [TIME_ENTRY_ROW_FIELD.schedule]: ['start_time', 'end_time', 'minutes'],
  [TIME_ENTRY_ROW_FIELD.context]: ['registry_id', 'opportunity_id', 'work_order_id', 'task_id', 'work_order_stage_id'],
}

/** The fields whose message is on screen: the open editor's, or (none open) each closed row's own key. */
function visibleFields(openRow: string | null): readonly string[] {
  if (openRow === null) {
    return Object.values(TIME_ENTRY_ROW_FIELD)
  }
  return ROW_EDITOR_FIELDS[openRow] ?? [openRow]
}

/**
 * The first refused field's message when no row on screen carries it (e.g. a
 * closed "Orario" row refused on "Alle ore"): the caller toasts it, so a
 * refusal is never silent.
 */
export function hiddenErrorMessage(errors: FieldErrors<TimeEntryFormValues>, openRow: string | null): string | null {
  const shown = visibleFields(openRow)
  for (const field of Object.keys(errors)) {
    const message = get(errors, `${field}.message`)
    if (!shown.includes(field) && typeof message === 'string' && message !== '') {
      return message
    }
  }
  return null
}
