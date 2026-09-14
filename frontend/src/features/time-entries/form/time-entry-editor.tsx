/**
 * The time entry create/edit card (spec 0122 MT-F2, D-2 fidelity to q-net's
 * `WorkActivityEditor`): header with the selected type's colored icon, then
 * Titolo, Data|Tipo, "Contesto segnatempo", Dalle/Alle/Tempo and Note. Pure
 * composition — every non-render concern lives in `useTimeEntryForm`; this
 * file only lays the fields out and forwards their handlers.
 *
 * `showTitle`/`showContext` (default true) let the Task detail's embed
 * (MT-F6, D-9: "editor senza titolo e senza collegamenti") reuse this exact
 * card instead of duplicating the layout.
 */

import { useTranslation } from 'react-i18next'
import { useWatch, type Control } from 'react-hook-form'
import { Clock, Info } from 'lucide-react'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { MetaField } from '@/features/authorization/MetaField'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { minutesToTimeValue, timeValueToMinutes } from '@/features/time-entries/time-entry-format'
import { TimeEntryContextFields } from '@/features/time-entries/form/time-entry-context-fields'
import { TimeEntryTypePicker, useTimeEntryTypeOptions } from '@/features/time-entries/form/time-entry-type-picker'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import type { TaskDetailWithPermissions } from '@/features/tasks/types'
import type { TimeEntryFormValues } from '@/features/time-entries/form/time-entry-schema'
import { cn } from '@/lib/utils'

interface TimeEntryEditorProps {
  control: Control<TimeEntryFormValues>
  disabled?: boolean
  showTitle?: boolean
  showContext?: boolean
  headerTitle: string
  headerDescription?: string
  isTaskLinked: boolean
  taskDetail: TaskDetailWithPermissions | undefined
  registry: RelationFieldRef | null
  opportunity: RelationFieldRef | null
  workOrder: RelationFieldRef | null
  task: RelationFieldRef | null
  onRegistryChange: () => void
  onOpportunityItemChange: (item: ForSelectItem | null) => void
  onWorkOrderItemChange: (item: ForSelectItem | null) => void
  onStartTimeChange: (value: string) => void
  onEndTimeChange: (value: string) => void
  serverError: string | null
}

/** The header chip: the currently picked type's icon/color, `Clock` while unpicked (D-2). */
function EditorHeaderIcon({ control }: { control: Control<TimeEntryFormValues> }) {
  const taskTypeId = useWatch({ control, name: 'task_type_id' })
  const { options } = useTimeEntryTypeOptions()
  const selected = options.find((option) => option.id === taskTypeId)

  if (!selected) {
    return (
      <span className="flex size-9 shrink-0 items-center justify-center rounded-full border border-border bg-background text-muted-foreground">
        <Clock className="size-4" aria-hidden="true" />
      </span>
    )
  }

  return (
    <span className={cn('flex size-9 shrink-0 items-center justify-center rounded-full border border-current', badgeColorClass(selected.meta.color))}>
      <DynamicIcon name={selected.meta.icon} className="size-4" />
    </span>
  )
}

export function TimeEntryEditor({
  control,
  disabled = false,
  showTitle = true,
  showContext = true,
  headerTitle,
  headerDescription,
  isTaskLinked,
  taskDetail,
  registry,
  opportunity,
  workOrder,
  task,
  onRegistryChange,
  onOpportunityItemChange,
  onWorkOrderItemChange,
  onStartTimeChange,
  onEndTimeChange,
  serverError,
}: TimeEntryEditorProps) {
  const { t } = useTranslation()
  const titleReadOnly = disabled || isTaskLinked

  return (
    <section className="rounded-xl border bg-card p-4 shadow-sm">
      <div className="mb-4 flex items-start gap-3">
        <EditorHeaderIcon control={control} />
        <div className="min-w-0">
          <h3 className="text-sm font-semibold tracking-tight text-foreground">{headerTitle}</h3>
          {headerDescription ? <p className="mt-0.5 text-xs text-muted-foreground">{headerDescription}</p> : null}
        </div>
      </div>

      {showTitle ? (
        <div className="mb-4">
          <MetaField control={control} name="title" metaKey="title" label={t('timeEntries.form.title')} required={!isTaskLinked}>
            {({ field, disabled: fieldDisabled }) => (
              <FormControl>
                <Input
                  value={isTaskLinked ? (taskDetail?.title ?? '') : field.value}
                  onChange={(event) => field.onChange(event.target.value)}
                  onBlur={field.onBlur}
                  name={field.name}
                  disabled={fieldDisabled || titleReadOnly}
                  readOnly={titleReadOnly}
                />
              </FormControl>
            )}
          </MetaField>
        </div>
      ) : null}

      <div className="grid min-w-0 gap-4 md:grid-cols-2">
        <MetaField control={control} name="date" metaKey="date" label={t('timeEntries.form.date')} required>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <Input
                type="date"
                value={field.value}
                onChange={(event) => field.onChange(event.target.value)}
                onBlur={field.onBlur}
                name={field.name}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="task_type_id" metaKey="task_type_id" label={t('timeEntries.form.type')} required>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <TimeEntryTypePicker
                value={field.value}
                onChange={(id) => field.onChange(id)}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>
      </div>

      {showContext ? (
        <div className="mt-4">
          <TimeEntryContextFields
            control={control}
            disabled={disabled}
            registry={registry}
            opportunity={opportunity}
            workOrder={workOrder}
            task={task}
            isTaskLinked={isTaskLinked}
            taskDetail={taskDetail}
            onRegistryChange={onRegistryChange}
            onOpportunityItemChange={onOpportunityItemChange}
            onWorkOrderItemChange={onWorkOrderItemChange}
          />
        </div>
      ) : null}

      <div className="mt-4 grid min-w-0 gap-4 md:grid-cols-3">
        <MetaField control={control} name="start_time" metaKey="start_time" label={t('timeEntries.form.startTime')}>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <Input
                type="time"
                value={field.value ?? ''}
                onChange={(event) => onStartTimeChange(event.target.value)}
                onBlur={field.onBlur}
                name={field.name}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="end_time" metaKey="end_time" label={t('timeEntries.form.endTime')}>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <Input
                type="time"
                value={field.value ?? ''}
                onChange={(event) => onEndTimeChange(event.target.value)}
                onBlur={field.onBlur}
                name={field.name}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="minutes" metaKey="minutes" label={t('timeEntries.form.minutes')} required>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <Input
                type="time"
                value={minutesToTimeValue(field.value ?? 0)}
                onChange={(event) => field.onChange(timeValueToMinutes(event.target.value) ?? 0)}
                onBlur={field.onBlur}
                name={field.name}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>
      </div>

      <div className="mt-4">
        <MetaField control={control} name="notes" metaKey="notes" label={t('timeEntries.form.notes')}>
          {({ field, disabled: fieldDisabled }) => (
            <FormControl>
              <Textarea
                className="min-h-28"
                value={field.value ?? ''}
                onChange={(event) => field.onChange(event.target.value === '' ? null : event.target.value)}
                onBlur={field.onBlur}
                name={field.name}
                disabled={fieldDisabled || disabled}
              />
            </FormControl>
          )}
        </MetaField>
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
