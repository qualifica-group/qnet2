/**
 * AG Grid popup cell editor for the Task list's `work_order_stage` column
 * (spec 0156 D-8): the same OPEN-fasi option list the create/edit form's
 * picker already resolves (`useTaskWorkOrderStageOptions`), scoped to the
 * EDITED ROW's own commessa — never a `/for-select` resource like the generic
 * `relation` editor, since the option set is row-derived, not a flat catalog.
 * Without a commessa on the row there is nothing to pick from (the column
 * stays read-only server-side in that case too, per contract).
 */
import { useEffect, useRef } from 'react'
import type { CustomCellEditorProps } from 'ag-grid-react'
import { useTranslation } from 'react-i18next'
import { Check } from 'lucide-react'
import { cn } from '@/lib/utils'
import { useTaskWorkOrderStageOptions } from '@/features/tasks/use-task-work-order-stage-options'
import type { TableRow } from '@/features/table/types'
import type { TaskWorkOrderStageRef } from '@/features/tasks/types'

export function TaskWorkOrderStageCellEditor(
  props: CustomCellEditorProps<TableRow, TaskWorkOrderStageRef | null>,
) {
  const { t } = useTranslation()
  const { value, data, onValueChange, stopEditing } = props
  const listRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    listRef.current?.focus()
  }, [])

  const workOrderId = (data?.work_order as { id?: number } | null | undefined)?.id ?? null
  const { options, isLoading } = useTaskWorkOrderStageOptions(workOrderId, value ?? null)

  const pick = (option: TaskWorkOrderStageRef | null) => {
    if ((option?.id ?? null) !== (value?.id ?? null)) {
      onValueChange(option)
    }
    stopEditing()
  }

  return (
    <div
      ref={listRef}
      role="listbox"
      tabIndex={-1}
      aria-label={t('tasks.form.workOrderStage')}
      className="max-h-64 w-64 overflow-auto rounded-md border border-border bg-popover p-1 shadow-md outline-none"
    >
      {workOrderId === null ? (
        <p className="px-2.5 py-1 text-xs text-muted-foreground">{t('tasks.form.workOrderStageNoWorkOrder')}</p>
      ) : isLoading ? (
        <p className="px-2.5 py-1 text-xs text-muted-foreground">{t('tasks.form.selectPlaceholder')}</p>
      ) : (
        <>
          <button
            type="button"
            role="option"
            aria-selected={value === null || value === undefined}
            onClick={() => pick(null)}
            className={cn(
              'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
              'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
            )}
          >
            <Check
              className={cn('size-3.5 shrink-0', value ? 'opacity-0' : 'opacity-100')}
              aria-hidden="true"
            />
            <span className="truncate text-muted-foreground">{t('tasks.form.workOrderStageNoStage')}</span>
          </button>
          {options.map((option) => {
            const selected = value?.id === option.id
            return (
              <button
                key={option.id}
                type="button"
                role="option"
                aria-selected={selected}
                onClick={() => pick(option)}
                className={cn(
                  'flex w-full items-center gap-1.5 rounded-sm px-2.5 py-1 text-left text-xs',
                  'hover:bg-accent focus-visible:bg-accent focus-visible:outline-none',
                  selected && 'font-medium',
                )}
              >
                <Check className={cn('size-3.5 shrink-0', selected ? 'opacity-100' : 'opacity-0')} aria-hidden="true" />
                <span className="truncate">{option.name}</span>
              </button>
            )
          })}
        </>
      )}
    </div>
  )
}
