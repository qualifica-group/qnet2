/**
 * "Filtri segnatempo" drawer (spec 0122 D-13, AC-033): one collapsible
 * section per `TIME_ENTRY_FILTER_DEFINITIONS` entry, mirroring q-net's
 * `WorkActivitiesFiltersDrawer` (structure/density, D-2) but driven by the
 * static config already frozen in `time-entries-filters.ts` (no backend page
 * config to group into sections).
 */

import { useTranslation } from 'react-i18next'
import { RotateCcw } from 'lucide-react'
import {
  AsyncPaginatedMultiSelect,
  type AsyncPaginatedMultiSelectLabels,
} from '@/components/ui/async-paginated-multi-select'
import { AsyncPaginatedSelect, type AsyncPaginatedSelectLabels } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { TimeEntryFilterSection } from '@/features/time-entries/dashboard/time-entry-filter-section'
import { TimeEntriesTaskTypeFilter } from '@/features/time-entries/dashboard/time-entries-task-type-filter'
import { TimeEntryToggleFilter, type TimeEntryToggleOption } from '@/features/time-entries/dashboard/time-entry-toggle-filter'
import { DAILY_STATUS_META, dailyStatusBadgeClass } from '@/features/time-entries/time-entry-constants'
import {
  TIME_ENTRY_FILTER_DEFINITIONS,
  type TimeEntriesFiltersState,
} from '@/features/time-entries/time-entries-filters'
import type { DailyStatus } from '@/features/time-entries/types'

const DAILY_STATUS_VALUES: readonly DailyStatus[] = ['no_target', 'under_target', 'on_target', 'over_target']

function toValueArray(value: string | string[] | undefined): string[] {
  const entries = Array.isArray(value) ? value : typeof value === 'string' ? [value] : []
  return entries.map((entry) => entry.trim()).filter((entry) => entry.length > 0)
}

function toSinglePositiveId(value: string | string[] | undefined): number | null {
  const raw = toValueArray(value)[0]
  const parsed = Number(raw)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

interface TimeEntriesFiltersDrawerProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  filters: TimeEntriesFiltersState
  onFiltersChange: (next: TimeEntriesFiltersState) => void
  onReset: () => void
  canFilterUsers: boolean
}

export function TimeEntriesFiltersDrawer({
  open,
  onOpenChange,
  filters,
  onFiltersChange,
  onReset,
  canFilterUsers,
}: TimeEntriesFiltersDrawerProps) {
  const { t } = useTranslation()

  const setValue = (key: string, value: string | string[]) => {
    onFiltersChange({ ...filters, values: { ...filters.values, [key]: value } })
  }

  const forSelectLabels = (fieldLabelKey: string): AsyncPaginatedSelectLabels & AsyncPaginatedMultiSelectLabels => ({
    placeholder: t(`timeEntries.filters.fields.${fieldLabelKey}`),
    searchPlaceholder: t('timeEntries.filters.search'),
    empty: t('timeEntries.filters.noResults'),
    error: t('timeEntries.filters.loadError'),
    clearLabel: t('timeEntries.filters.clearSelection'),
    removeLabel: t('timeEntries.filters.removeSelection'),
    triggerLabel: t(`timeEntries.filters.fields.${fieldLabelKey}`),
    retry: t('timeEntries.page.retry'),
  })

  const dailyStatusOptions: TimeEntryToggleOption[] = DAILY_STATUS_VALUES.map((status) => {
    const meta = DAILY_STATUS_META[status]
    return {
      value: status,
      label: t(`timeEntries.dailyStatus.${meta.labelKey}`),
      icon: meta.icon,
      colorClassName: dailyStatusBadgeClass(status),
    }
  })

  const isActiveOptions: TimeEntryToggleOption[] = [
    { value: 'true', label: t('timeEntries.filters.isActiveOptions.true') },
    { value: 'false', label: t('timeEntries.filters.isActiveOptions.false') },
  ]

  const activeCount = TIME_ENTRY_FILTER_DEFINITIONS.reduce<number>((acc, definition) => {
    acc += toValueArray(filters.values[definition.key]).length
    return acc
  }, 0)

  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="left" className="w-screen overflow-y-auto p-0 sm:w-[80vw] lg:max-w-[560px]">
        <SheetHeader className="border-b">
          <SheetTitle>{t('timeEntries.filters.title')}</SheetTitle>
          <SheetDescription>{t('timeEntries.filters.description')}</SheetDescription>
        </SheetHeader>

        <div className="space-y-1 p-4">
          <div className="flex justify-end pb-2">
            <Button type="button" variant="outline" size="sm" disabled={activeCount === 0} onClick={onReset}>
              <RotateCcw className="size-4" aria-hidden="true" />
              {t('timeEntries.filters.reset')}
            </Button>
          </div>

          {TIME_ENTRY_FILTER_DEFINITIONS.map((definition) => {
            const rawValue = filters.values[definition.key]
            const title = t(`timeEntries.filters.fields.${definition.labelKey}`)

            if (definition.control === 'user') {
              const userId = toSinglePositiveId(rawValue)
              return (
                <TimeEntryFilterSection
                  key={definition.key}
                  icon={definition.icon}
                  title={title}
                  activeCount={userId !== null ? 1 : 0}
                >
                  <AsyncPaginatedSelect
                    resource="users"
                    value={userId}
                    onChange={(id) => setValue(definition.key, id !== null ? [String(id)] : [])}
                    disabled={!canFilterUsers}
                    showAvatar
                    labels={forSelectLabels(definition.labelKey)}
                  />
                </TimeEntryFilterSection>
              )
            }

            if (definition.control === 'for-select-multi' && definition.forSelectResource === 'task-types') {
              const value = toValueArray(rawValue)
              return (
                <TimeEntryFilterSection key={definition.key} icon={definition.icon} title={title} activeCount={value.length}>
                  <TimeEntriesTaskTypeFilter
                    value={value}
                    onChange={(next) => setValue(definition.key, next)}
                    aria-label={title}
                  />
                </TimeEntryFilterSection>
              )
            }

            if (definition.control === 'for-select-multi' && definition.forSelectResource) {
              const value = toValueArray(rawValue).map(Number)
              return (
                <TimeEntryFilterSection key={definition.key} icon={definition.icon} title={title} activeCount={value.length}>
                  <AsyncPaginatedMultiSelect
                    resource={definition.forSelectResource}
                    value={value}
                    onChange={(next) => setValue(definition.key, next.map(String))}
                    labels={forSelectLabels(definition.labelKey)}
                  />
                </TimeEntryFilterSection>
              )
            }

            if (definition.control === 'daily-status-multi') {
              const value = toValueArray(rawValue)
              return (
                <TimeEntryFilterSection key={definition.key} icon={definition.icon} title={title} activeCount={value.length}>
                  <TimeEntryToggleFilter
                    options={dailyStatusOptions}
                    value={value}
                    onChange={(next) => setValue(definition.key, next)}
                    aria-label={title}
                  />
                </TimeEntryFilterSection>
              )
            }

            const value = toValueArray(rawValue)
            return (
              <TimeEntryFilterSection key={definition.key} icon={definition.icon} title={title} activeCount={value.length}>
                <TimeEntryToggleFilter
                  options={isActiveOptions}
                  value={value}
                  onChange={(next) => setValue(definition.key, next)}
                  multiple={false}
                  aria-label={title}
                />
              </TimeEntryFilterSection>
            )
          })}
        </div>
      </SheetContent>
    </Sheet>
  )
}
