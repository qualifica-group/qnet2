/**
 * The time entry card embedded in a task (spec 0122 MT-F6/D-9, spec 0123
 * D-1): the Task detail's "Nuovo intervallo" and the Completa pop-up's
 * "Segnatempo" section. Header with the selected type's colored icon, then
 * Data|Tipo, Dalle/Alle/Tempo and Note — no title and no context links, the
 * task supplies both. The standalone record (detail and create) lays the same
 * field controls out as rows instead (spec 0226, `time-entry-record-sections`).
 */

import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Info } from 'lucide-react'
import {
  TimeEntryDateField,
  TimeEntryNotesField,
  TimeEntryScheduleFields,
  TimeEntryTypeField,
  type TimeEntryEditorFieldValues,
} from '@/features/time-entries/form/time-entry-fields'
import { TimeEntryTypeChip } from '@/features/time-entries/form/time-entry-type-chip'
import { useTimeEntryTypeOptions } from '@/features/time-entries/form/time-entry-type-picker'

interface TimeEntryEditorProps {
  control: Control<TimeEntryEditorFieldValues>
  disabled?: boolean
  headerTitle: string
  headerDescription?: string
  onStartTimeChange: (value: string) => void
  onEndTimeChange: (value: string) => void
  serverError: string | null
}

/** The header chip: the currently picked type's icon/color, `Clock` while unpicked (D-2). */
function EditorHeaderIcon({ control }: { control: Control<TimeEntryEditorFieldValues> }) {
  const taskTypeId = useWatch({ control, name: 'task_type_id' })
  const { options } = useTimeEntryTypeOptions()
  const selected = options.find((option) => option.id === taskTypeId)

  return <TimeEntryTypeChip type={selected ? selected.meta : null} />
}

export function TimeEntryEditor({
  control,
  disabled = false,
  headerTitle,
  headerDescription,
  onStartTimeChange,
  onEndTimeChange,
  serverError,
}: TimeEntryEditorProps) {
  const { t } = useTranslation()

  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <div className="mb-4 flex items-start gap-3">
        <EditorHeaderIcon control={control} />
        <div className="min-w-0">
          <h3 className="text-sm font-semibold tracking-tight text-foreground">{headerTitle}</h3>
          {headerDescription ? <p className="mt-0.5 text-xs text-muted-foreground">{headerDescription}</p> : null}
        </div>
      </div>

      <div className="grid min-w-0 gap-4 md:grid-cols-2">
        <TimeEntryDateField control={control} disabled={disabled} />
        <TimeEntryTypeField control={control} disabled={disabled} />
      </div>

      <div className="mt-4">
        <TimeEntryScheduleFields
          control={control}
          disabled={disabled}
          onStartTimeChange={onStartTimeChange}
          onEndTimeChange={onEndTimeChange}
        />
      </div>

      <div className="mt-4">
        <TimeEntryNotesField control={control} disabled={disabled} />
      </div>

      <div className="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
        <Info className="size-4 shrink-0" aria-hidden="true" />
        <span>{t('timeEntries.form.minutesHint')}</span>
      </div>

      {serverError ? (
        <p className="mt-4 text-sm font-medium text-destructive" role="alert">
          {serverError}
        </p>
      ) : null}
    </section>
  )
}
