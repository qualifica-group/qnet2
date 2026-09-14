/**
 * Pure filter/sort state for the `/time-entries` dashboard (spec 0122 D-13,
 * AC-033). No React here: `use-time-entries-filters.ts` wraps this in a hook
 * (localStorage + period navigation), so both layers stay independently
 * testable. The 8 definitions below are the drawer's filters; "Periodo" is
 * NOT one of them — it is driven by the dedicated period selector/navigation
 * (`time-entry-period.ts`) and therefore never counts toward the badge or
 * chip list (AC-032 vs AC-033 are two separate concerns).
 */

import { Activity, Briefcase, Building2, ClipboardList, Tag, Target, User, type LucideIcon } from 'lucide-react'
import { DAILY_STATUS_META } from '@/features/time-entries/time-entry-constants'
import type {
  DailyStatus,
  TimeEntriesFilterParams,
  TimeEntriesListParams,
  TimeEntriesSortBy,
} from '@/features/time-entries/types'
import type { TimeEntriesPeriodUnit } from '@/features/time-entries/time-entry-period'

/** localStorage key the whole filter+sort state round-trips through (D-13). */
export const TIME_ENTRIES_FILTERS_STORAGE_KEY = 'time-entries-page-filters'

export const TIME_ENTRIES_DEFAULT_SORT_BY: TimeEntriesSortBy = 'date'
export const TIME_ENTRIES_DEFAULT_SORT_DIRECTION: 'asc' | 'desc' = 'asc'
export const TIME_ENTRIES_DEFAULT_PERIOD_UNIT: TimeEntriesPeriodUnit = 'week'

export interface TimeEntriesFiltersState {
  values: Record<string, string | string[]>
  sortBy: string
  sortDirection: 'asc' | 'desc'
}

export type TimeEntryFilterControl = 'user' | 'for-select-multi' | 'daily-status-multi' | 'boolean'

export interface TimeEntryFilterDefinition {
  key: string
  /** i18n key under `timeEntries.filters.fields.*`. */
  labelKey: string
  icon: LucideIcon
  control: TimeEntryFilterControl
  /** Resource segment of `GET /api/{resource}/for-select`, for `control: 'for-select-multi'`. */
  forSelectResource?: string
}

/** Static page config (D-13): Utente, Tipo, Cliente, Commessa, Opportunità, Task, Stato target, Attività. */
export const TIME_ENTRY_FILTER_DEFINITIONS: readonly TimeEntryFilterDefinition[] = [
  { key: 'user_id', labelKey: 'user', icon: User, control: 'user' },
  { key: 'task_type_ids', labelKey: 'type', icon: Tag, control: 'for-select-multi', forSelectResource: 'task-types' },
  { key: 'registry_ids', labelKey: 'registry', icon: Building2, control: 'for-select-multi', forSelectResource: 'registries' },
  { key: 'work_order_ids', labelKey: 'workOrder', icon: Briefcase, control: 'for-select-multi', forSelectResource: 'work-orders' },
  { key: 'opportunity_ids', labelKey: 'opportunity', icon: Target, control: 'for-select-multi', forSelectResource: 'opportunities' },
  { key: 'task_ids', labelKey: 'task', icon: ClipboardList, control: 'for-select-multi', forSelectResource: 'tasks' },
  { key: 'daily_statuses', labelKey: 'dailyStatus', icon: Activity, control: 'daily-status-multi' },
  { key: 'is_active', labelKey: 'isActive', icon: Activity, control: 'boolean' },
] as const

export interface TimeEntrySortOption {
  value: TimeEntriesSortBy
  /** i18n key under `timeEntries.sorting.fields.*`. */
  labelKey: string
}

/** Static sorts (D-13): Giorno/Target/Attività. */
export const TIME_ENTRY_SORT_OPTIONS: readonly TimeEntrySortOption[] = [
  { value: 'date', labelKey: 'date' },
  { value: 'target_minutes', labelKey: 'targetMinutes' },
  { value: 'is_active', labelKey: 'isActive' },
] as const

const DAILY_STATUS_VALUES: readonly DailyStatus[] = [
  'no_target',
  'under_target',
  'on_target',
  'over_target',
]

function isTimeEntriesSortBy(value: string): value is TimeEntriesSortBy {
  return TIME_ENTRY_SORT_OPTIONS.some((option) => option.value === value)
}

export function createDefaultTimeEntriesFilters(): TimeEntriesFiltersState {
  return {
    values: { period_preset: [TIME_ENTRIES_DEFAULT_PERIOD_UNIT] },
    sortBy: TIME_ENTRIES_DEFAULT_SORT_BY,
    sortDirection: TIME_ENTRIES_DEFAULT_SORT_DIRECTION,
  }
}

function normalizeScalarValue(value: unknown): string {
  if (typeof value === 'string') {
    return value.trim()
  }
  if (typeof value === 'number' || typeof value === 'boolean') {
    return String(value)
  }
  return ''
}

function normalizeFilterEntry(value: unknown): string | string[] {
  if (Array.isArray(value)) {
    return value.map(normalizeScalarValue).filter((entry) => entry.length > 0)
  }
  return normalizeScalarValue(value)
}

function normalizeFilterValues(value: unknown): Record<string, string | string[]> {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return {}
  }
  return Object.fromEntries(
    Object.entries(value as Record<string, unknown>).map(([key, entry]) => [
      key,
      normalizeFilterEntry(entry),
    ]),
  )
}

/** Guards a parsed `localStorage` payload: anything hand-edited or from an older shape falls back to defaults. */
export function normalizeTimeEntriesFiltersState(value: unknown): TimeEntriesFiltersState {
  if (!value || typeof value !== 'object' || Array.isArray(value)) {
    return createDefaultTimeEntriesFilters()
  }

  const record = value as Record<string, unknown>
  const values = normalizeFilterValues(record.values)
  const sortBy = normalizeScalarValue(record.sortBy)

  return {
    values: Object.keys(values).length > 0 ? values : createDefaultTimeEntriesFilters().values,
    sortBy: isTimeEntriesSortBy(sortBy) ? sortBy : TIME_ENTRIES_DEFAULT_SORT_BY,
    sortDirection: record.sortDirection === 'desc' ? 'desc' : TIME_ENTRIES_DEFAULT_SORT_DIRECTION,
  }
}

export function parseStoredTimeEntriesFilters(raw: string | null): TimeEntriesFiltersState {
  if (!raw) {
    return createDefaultTimeEntriesFilters()
  }
  try {
    return normalizeTimeEntriesFiltersState(JSON.parse(raw))
  } catch {
    return createDefaultTimeEntriesFilters()
  }
}

function hasValue(entry: string | string[] | undefined): boolean {
  if (Array.isArray(entry)) {
    return entry.some((value) => value.trim().length > 0)
  }
  return typeof entry === 'string' && entry.trim().length > 0
}

/** Counts only the 8 drawer filters — never "Periodo" (see module docblock). */
export function countActiveTimeEntriesFilters(filters: TimeEntriesFiltersState): number {
  return TIME_ENTRY_FILTER_DEFINITIONS.reduce(
    (count, definition) => (hasValue(filters.values[definition.key]) ? count + 1 : count),
    0,
  )
}

/** Removes one filter's value, e.g. from a chip's dismiss button. Returns a NEW `values` object. */
export function removeTimeEntriesFilterValue(
  values: Record<string, string | string[]>,
  key: string,
): Record<string, string | string[]> {
  const next = { ...values }
  delete next[key]
  return next
}

function firstValue(value: string | string[] | undefined): string | undefined {
  const raw = Array.isArray(value) ? value[0] : value
  return typeof raw === 'string' && raw.trim().length > 0 ? raw.trim() : undefined
}

function toPositiveInt(value: string | string[] | undefined): number | undefined {
  const parsed = Number(firstValue(value))
  return Number.isInteger(parsed) && parsed > 0 ? parsed : undefined
}

function toPositiveIntArray(value: string | string[] | undefined): number[] | undefined {
  const entries = Array.isArray(value) ? value : typeof value === 'string' ? [value] : []
  const parsed = entries.map(Number).filter((entry) => Number.isInteger(entry) && entry > 0)
  return parsed.length > 0 ? parsed : undefined
}

function toDailyStatusArray(value: string | string[] | undefined): DailyStatus[] | undefined {
  const entries = Array.isArray(value) ? value : typeof value === 'string' ? [value] : []
  const valid = entries.filter((entry): entry is DailyStatus =>
    DAILY_STATUS_VALUES.includes(entry as DailyStatus),
  )
  return valid.length > 0 ? valid : undefined
}

function toBoolean(value: string | string[] | undefined): boolean | undefined {
  const raw = firstValue(value)
  if (raw === 'true') return true
  if (raw === 'false') return false
  return undefined
}

function toPeriodPreset(value: string | string[] | undefined): TimeEntriesPeriodUnit | undefined {
  const raw = firstValue(value)
  return raw === 'day' || raw === 'week' || raw === 'month' || raw === 'year' ? raw : undefined
}

/** Converts the UI state into the backend filter params (D-3 e: arrays for every multi-value filter). */
export function buildTimeEntriesFilterParams(filters: TimeEntriesFiltersState): TimeEntriesFilterParams {
  const { values } = filters

  return {
    user_id: toPositiveInt(values.user_id),
    period_preset: toPeriodPreset(values.period_preset),
    date_from: firstValue(values.date_from),
    date_to: firstValue(values.date_to),
    task_type_ids: toPositiveIntArray(values.task_type_ids),
    registry_ids: toPositiveIntArray(values.registry_ids),
    opportunity_ids: toPositiveIntArray(values.opportunity_ids),
    work_order_ids: toPositiveIntArray(values.work_order_ids),
    task_ids: toPositiveIntArray(values.task_ids),
    daily_statuses: toDailyStatusArray(values.daily_statuses),
    is_active: toBoolean(values.is_active),
  }
}

/** `buildTimeEntriesFilterParams` plus sort/pagination, for `GET /api/time-entries`. */
export function buildTimeEntriesListParams(
  filters: TimeEntriesFiltersState,
  page: number,
  perPage: number,
): TimeEntriesListParams {
  return {
    ...buildTimeEntriesFilterParams(filters),
    sort_by: isTimeEntriesSortBy(filters.sortBy) ? filters.sortBy : TIME_ENTRIES_DEFAULT_SORT_BY,
    sort_direction: filters.sortDirection,
    page,
    per_page: perPage,
  }
}

export interface TimeEntryFilterChip {
  key: string
  label: string
}

/** Resolves one filter value to its display label; F2 supplies for-select/user label lookups. */
export type TimeEntryFilterValueLabelResolver = (
  definition: TimeEntryFilterDefinition,
  rawValue: string,
) => string

/** Handles the two statically-known controls; id-backed ones fall back to the raw id. */
function defaultFilterValueLabel(
  definition: TimeEntryFilterDefinition,
  rawValue: string,
  translate: (key: string) => string,
): string {
  if (definition.control === 'boolean') {
    return translate(`timeEntries.filters.isActiveOptions.${rawValue === 'true' ? 'true' : 'false'}`)
  }
  if (definition.control === 'daily-status-multi') {
    const meta = DAILY_STATUS_META[rawValue as DailyStatus]
    return meta ? translate(`timeEntries.dailyStatus.${meta.labelKey}`) : rawValue
  }
  return rawValue
}

/** Builds the chip list for the drawer's 8 filters (AC-033). Order follows `TIME_ENTRY_FILTER_DEFINITIONS`. */
export function buildTimeEntriesFilterChips(
  filters: TimeEntriesFiltersState,
  translate: (key: string) => string,
  resolveValueLabel: TimeEntryFilterValueLabelResolver = (definition, rawValue) =>
    defaultFilterValueLabel(definition, rawValue, translate),
): TimeEntryFilterChip[] {
  const chips: TimeEntryFilterChip[] = []

  for (const definition of TIME_ENTRY_FILTER_DEFINITIONS) {
    const rawValue = filters.values[definition.key]
    if (!hasValue(rawValue)) {
      continue
    }

    const entries = Array.isArray(rawValue) ? rawValue : [rawValue]
    const labels = entries
      .filter((entry) => entry.trim().length > 0)
      .map((entry) => resolveValueLabel(definition, entry))

    chips.push({
      key: definition.key,
      label: `${translate(`timeEntries.filters.fields.${definition.labelKey}`)}: ${labels.join(', ')}`,
    })
  }

  return chips
}
