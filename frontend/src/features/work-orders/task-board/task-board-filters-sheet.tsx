/**
 * The single place where the board's filters are edited (user directive
 * 2026-09-22, same surface as Gestione Richieste's `RequestReportFiltersDialog`):
 * the toolbar only shows what is applied, this sheet holds every control.
 * The edits live in a DRAFT until "Applica": `TaskBoardFiltersForm` is mounted
 * only while the sheet is open, so every open starts again from the applied
 * filters and "Annulla" simply throws the draft away.
 */

import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { SlidersHorizontal } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import {
  SearchableMultiSelect,
  type SearchableMultiSelectLabels,
  type SearchableMultiSelectOption,
} from '@/components/ui/searchable-multi-select'
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet'
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

/** Brand-tinted header strip with the icon chip, same band as the Gestione Richieste filter sheet. */
const HEADER_BAND_CLASS =
  'flex items-start gap-3 border-b bg-gradient-to-br from-card to-primary/[0.06] px-4 pt-4 pr-12 pb-3.5'
const HEADER_ICON_CLASS =
  'flex size-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary ring-1 ring-primary/15'
const FIELD_LABEL_CLASS = 'text-xs font-medium'

const STATUS_OPTIONS: readonly TaskBoardStatusFilter[] = ['open', 'completed', 'blocked', 'all']
const DUE_OPTIONS: readonly TaskBoardDueFilter[] = ['all', 'today', 'overdue', 'this_week']
const ASSIGNMENT_OPTIONS: readonly TaskBoardAssignmentFilter[] = ['all', 'assigned_to_me', 'requested_by_me']

type IdFilterKey = 'taskTypeIds' | 'taskPriorityIds' | 'requesterIds' | 'assigneeIds' | 'watcherIds'

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
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent className="gap-0" defaultWidth={FILTERS_SHEET_DEFAULT_WIDTH} storageKey="sheet-width:task-board-filters">
        <div className={HEADER_BAND_CLASS}>
          <span aria-hidden="true" className={HEADER_ICON_CLASS}>
            <SlidersHorizontal className="size-4.5" />
          </span>
          <SheetHeader className="flex-1 gap-1 p-0">
            <SheetTitle className="text-sm">{t('workOrders.taskBoard.filters.sheetTitle')}</SheetTitle>
            <SheetDescription className="text-xs">{t('workOrders.taskBoard.filters.sheetDescription')}</SheetDescription>
          </SheetHeader>
        </div>

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
      </SheetContent>
    </Sheet>
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
        className="flex flex-1 flex-col gap-4 overflow-y-auto bg-surface p-4"
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

      <div className="flex flex-wrap items-center gap-2 border-t bg-gradient-to-t from-primary/[0.05] to-transparent px-4 py-3">
        <Button
          type="button"
          size="sm"
          variant="ghost"
          className="mr-auto"
          onClick={() => setDraft({ ...DEFAULT_TASK_BOARD_FILTERS, search: draft.search })}
        >
          {t('workOrders.taskBoard.filters.reset')}
        </Button>
        <Button type="button" size="sm" variant="outline" className="bg-card" onClick={onCancel}>
          {t('common.cancel')}
        </Button>
        <Button type="submit" form="task-board-filters-form" size="sm" className="min-w-24 shadow-sm shadow-primary/20">
          {t('workOrders.taskBoard.filters.apply')}
        </Button>
      </div>
    </>
  )
}
