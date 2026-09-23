/**
 * The single place where the board's filters are edited (user directive
 * 2026-09-22, the shared `FiltersSheet` Gestione Richieste also uses):
 * the toolbar only shows what is applied, this sheet holds every control.
 * The edits live in a DRAFT until "Applica": `TaskBoardFiltersForm` is mounted
 * only while the sheet is open, so every open starts again from the applied
 * filters and "Annulla" simply throws the draft away.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { FILTERS_SHEET_BODY_CLASS, FiltersSheet, FiltersSheetFooter } from '@/components/ui/filters-sheet'
import { Label } from '@/components/ui/label'
import {
  SearchableMultiSelect,
  type SearchableMultiSelectLabels,
  type SearchableMultiSelectOption,
} from '@/components/ui/searchable-multi-select'
import { TaskBoardSegmentedField } from '@/features/work-orders/task-board/task-board-segmented-field'
import {
  DEFAULT_TASK_BOARD_FILTERS,
  type TaskBoardFilterOptions,
} from '@/features/work-orders/task-board/task-board-filters'
import type {
  TaskBoardAssignmentFilter,
  TaskBoardDueFilter,
  TaskBoardFilters,
  TaskBoardStatusFilter,
} from '@/features/work-orders/task-board/types'

const FILTERS_SHEET_DEFAULT_WIDTH = 420

const FIELD_LABEL_CLASS = 'text-xs font-medium'

const STATUS_OPTIONS: readonly TaskBoardStatusFilter[] = ['open', 'completed', 'blocked', 'all']
const DUE_OPTIONS: readonly TaskBoardDueFilter[] = ['all', 'today', 'overdue', 'this_week']
const ASSIGNMENT_OPTIONS: readonly TaskBoardAssignmentFilter[] = ['all', 'assigned_to_me', 'requested_by_me']

type IdFilterKey =
  | 'taskStatusIds'
  | 'taskTypeIds'
  | 'taskPriorityIds'
  | 'taskImportanceIds'
  | 'requesterIds'
  | 'assigneeIds'
  | 'watcherIds'

interface IdFilterField {
  key: IdFilterKey
  label: string
  placeholder: string
  options: { id: number; name: string }[]
}

function toOptions(items: { id: number; name: string }[]): SearchableMultiSelectOption[] {
  return items.map((item) => ({ value: String(item.id), label: item.name }))
}

export interface TaskBoardFiltersSheetProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  filters: TaskBoardFilters
  options: TaskBoardFilterOptions
  onApply: (filters: TaskBoardFilters) => void
}

export function TaskBoardFiltersSheet({ open, onOpenChange, filters, options, onApply }: TaskBoardFiltersSheetProps) {
  const { t } = useTranslation()

  return (
    <FiltersSheet
      open={open}
      onOpenChange={onOpenChange}
      title={t('workOrders.taskBoard.filters.sheetTitle')}
      description={t('workOrders.taskBoard.filters.sheetDescription')}
      defaultWidth={FILTERS_SHEET_DEFAULT_WIDTH}
      storageKey="sheet-width:task-board-filters"
    >
      {open ? (
        <TaskBoardFiltersForm
          initial={filters}
          options={options}
          onCancel={() => onOpenChange(false)}
          onApply={(next) => {
            onApply(next)
            onOpenChange(false)
          }}
        />
      ) : null}
    </FiltersSheet>
  )
}

interface TaskBoardFiltersFormProps {
  initial: TaskBoardFilters
  options: TaskBoardFilterOptions
  onCancel: () => void
  onApply: (filters: TaskBoardFilters) => void
}

function TaskBoardFiltersForm({ initial, options, onCancel, onApply }: TaskBoardFiltersFormProps) {
  const { t } = useTranslation()
  const [draft, setDraft] = useState<TaskBoardFilters>(initial)
  const patch = (next: Partial<TaskBoardFilters>) => setDraft((current) => ({ ...current, ...next }))

  const pickerLabels = (placeholder: string): SearchableMultiSelectLabels => ({
    placeholder,
    allSelected: placeholder,
    searchPlaceholder: t('workOrders.taskBoard.filters.picker.search'),
    selectAll: t('workOrders.taskBoard.filters.picker.selectAll'),
    noMatch: t('workOrders.taskBoard.filters.picker.noMatch'),
    clear: t('workOrders.taskBoard.filters.picker.clear'),
    count: (selected, total) => t('workOrders.taskBoard.filters.picker.count', { selected, total }),
  })

  const idFields: IdFilterField[] = [
    {
      key: 'taskStatusIds',
      label: t('workOrders.taskBoard.filters.taskStatus'),
      placeholder: t('workOrders.taskBoard.filters.taskStatusPlaceholder'),
      options: options.taskStatuses,
    },
    {
      key: 'taskTypeIds',
      label: t('workOrders.taskBoard.filters.type'),
      placeholder: t('workOrders.taskBoard.filters.typePlaceholder'),
      options: options.taskTypes,
    },
    {
      key: 'taskPriorityIds',
      label: t('workOrders.taskBoard.filters.priority'),
      placeholder: t('workOrders.taskBoard.filters.priorityPlaceholder'),
      options: options.taskPriorities,
    },
    {
      key: 'taskImportanceIds',
      label: t('workOrders.taskBoard.filters.importance'),
      placeholder: t('workOrders.taskBoard.filters.importancePlaceholder'),
      options: options.taskImportances,
    },
    {
      key: 'requesterIds',
      label: t('workOrders.taskBoard.filters.requester'),
      placeholder: t('workOrders.taskBoard.filters.requesterPlaceholder'),
      options: options.requesters,
    },
    {
      key: 'assigneeIds',
      label: t('workOrders.taskBoard.filters.assignee'),
      placeholder: t('workOrders.taskBoard.filters.assigneePlaceholder'),
      options: options.assignees,
    },
    {
      key: 'watcherIds',
      label: t('workOrders.taskBoard.filters.watcher'),
      placeholder: t('workOrders.taskBoard.filters.watcherPlaceholder'),
      options: options.watchers,
    },
  ]

  return (
    <>
      <form
        id="task-board-filters-form"
        className={FILTERS_SHEET_BODY_CLASS}
        onSubmit={(event) => {
          event.preventDefault()
          onApply(draft)
        }}
      >
        <TaskBoardSegmentedField
          label={t('workOrders.taskBoard.filters.status')}
          value={draft.status}
          options={STATUS_OPTIONS.map((value) => ({ value, label: t(`workOrders.taskBoard.filters.statusOption.${value}`) }))}
          onChange={(status) => patch({ status })}
        />
        <TaskBoardSegmentedField
          label={t('workOrders.taskBoard.filters.due')}
          value={draft.due}
          options={DUE_OPTIONS.map((value) => ({ value, label: t(`workOrders.taskBoard.filters.dueOption.${value}`) }))}
          onChange={(due) => patch({ due })}
        />
        <TaskBoardSegmentedField
          label={t('workOrders.taskBoard.filters.assignment')}
          value={draft.assignment}
          options={ASSIGNMENT_OPTIONS.map((value) => ({
            value,
            label: t(`workOrders.taskBoard.filters.assignmentOption.${value}`),
          }))}
          onChange={(assignment) => patch({ assignment })}
        />

        {idFields.map((field) => (
          <div key={field.key} className="flex flex-col gap-1.5">
            <Label htmlFor={`task-board-filter-${field.key}`} className={FIELD_LABEL_CLASS}>
              {field.label}
            </Label>
            <SearchableMultiSelect
              id={`task-board-filter-${field.key}`}
              options={toOptions(field.options)}
              value={draft[field.key].map(String)}
              onChange={(next) => patch({ [field.key]: next.map(Number) })}
              labels={pickerLabels(field.placeholder)}
              disabled={field.options.length === 0}
            />
          </div>
        ))}
      </form>

      <FiltersSheetFooter
        formId="task-board-filters-form"
        labels={{
          reset: t('workOrders.taskBoard.filters.reset'),
          cancel: t('common.cancel'),
          apply: t('workOrders.taskBoard.filters.apply'),
        }}
        onReset={() => setDraft({ ...DEFAULT_TASK_BOARD_FILTERS, search: draft.search })}
        onCancel={onCancel}
      />
    </>
  )
}
