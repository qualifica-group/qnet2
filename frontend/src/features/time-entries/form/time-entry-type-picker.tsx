/**
 * "Tipo" picker (spec 0122 D-2/MT-F2): a row of round, icon-only buttons — one
 * per `task_type` — matching q-net's `TaskTypeIconPicker` 1:1 in flow (pick
 * replaces the value, tooltip carries the label) with qnet-2's own token
 * (`badgeColorClass`, `size-9` compact default) instead of q-net's ad-hoc
 * per-type style.
 */
/* eslint-disable react-refresh/only-export-components -- `useTimeEntryTypeOptions`
   is the shared data hook behind both this picker and the editor's header icon. */

import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip'
import { badgeColorClass } from '@/features/table/cell-renderers'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { flattenForSelectPages, useForSelect } from '@/features/for-select/use-for-select'
import {
  TASK_TYPES_FOR_SELECT_RESOURCE,
  type TaskTypeForSelectItem,
} from '@/features/task-types/for-select-api'
import { cn } from '@/lib/utils'

/**
 * The full catalog (D-2 seed: 7 rows), shared by the picker's own buttons and
 * the editor's header icon so both read the same list without a duplicate
 * network request (identical `useForSelect` query key, deduplicated by
 * TanStack Query).
 */
export function useTimeEntryTypeOptions() {
  const query = useForSelect({ resource: TASK_TYPES_FOR_SELECT_RESOURCE, search: '' })
  const options = flattenForSelectPages(query.data?.pages) as TaskTypeForSelectItem[]
  return { options, isPending: query.isPending, isError: query.isError, refetch: query.refetch }
}

interface TimeEntryTypePickerProps {
  value: number | null
  onChange: (id: number) => void
  disabled?: boolean
  id?: string
  'aria-describedby'?: string
  'aria-invalid'?: boolean
}

export function TimeEntryTypePicker({
  value,
  onChange,
  disabled = false,
  id,
  'aria-describedby': ariaDescribedBy,
  'aria-invalid': ariaInvalid,
}: TimeEntryTypePickerProps) {
  const { t } = useTranslation()
  const { options, isPending, isError, refetch } = useTimeEntryTypeOptions()

  if (isPending) {
    return (
      <div className="flex items-center gap-2" aria-hidden="true">
        {Array.from({ length: 5 }).map((_unused, index) => (
          <Skeleton key={index} className="size-9 rounded-full" />
        ))}
      </div>
    )
  }

  if (isError) {
    return (
      <div className="flex items-center gap-2">
        <p className="text-sm text-destructive" role="alert">
          {t('timeEntries.page.loadError')}
        </p>
        <button type="button" onClick={() => void refetch()} className="text-sm font-medium text-primary underline-offset-4 hover:underline">
          {t('common.retry')}
        </button>
      </div>
    )
  }

  return (
    <TooltipProvider>
      <div
        id={id}
        role="radiogroup"
        aria-label={t('timeEntries.form.type')}
        aria-describedby={ariaDescribedBy}
        aria-invalid={ariaInvalid}
        className="flex flex-wrap items-center gap-2"
      >
        {options.map((option) => {
          const isSelected = option.id === value
          const colorClass = badgeColorClass(option.meta.color)

          return (
            <Tooltip key={option.id}>
              <TooltipTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  role="radio"
                  aria-checked={isSelected}
                  aria-label={option.label}
                  disabled={disabled}
                  onClick={() => onChange(option.id)}
                  className={cn(
                    'size-9 rounded-full border p-0',
                    isSelected ? cn(colorClass, 'border-current') : 'border-border bg-background text-muted-foreground',
                  )}
                >
                  <DynamicIcon name={option.meta.icon} className="size-4" />
                </Button>
              </TooltipTrigger>
              <TooltipContent side="top">{option.label}</TooltipContent>
            </Tooltip>
          )
        })}
      </div>
    </TooltipProvider>
  )
}
