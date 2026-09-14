import { describe, expect, it } from 'vitest'
import {
  buildTimeEntriesFilterChips,
  buildTimeEntriesFilterParams,
  buildTimeEntriesListParams,
  countActiveTimeEntriesFilters,
  createDefaultTimeEntriesFilters,
  normalizeTimeEntriesFiltersState,
  parseStoredTimeEntriesFilters,
  removeTimeEntriesFilterValue,
  TIME_ENTRY_FILTER_DEFINITIONS,
  type TimeEntriesFiltersState,
} from '@/features/time-entries/time-entries-filters'

/** Spec 0122 AC-033: filters state -> query params, chips, count, persistence shape. */

const translate = (key: string) => key

describe('createDefaultTimeEntriesFilters', () => {
  it('defaults to the current-week preset and sort_by=date asc (D-13)', () => {
    const defaults = createDefaultTimeEntriesFilters()

    expect(defaults.values.period_preset).toEqual(['week'])
    expect(defaults.sortBy).toBe('date')
    expect(defaults.sortDirection).toBe('asc')
  })
})

describe('normalizeTimeEntriesFiltersState / parseStoredTimeEntriesFilters', () => {
  it('falls back to defaults for a non-object payload', () => {
    expect(normalizeTimeEntriesFiltersState(null)).toEqual(createDefaultTimeEntriesFilters())
    expect(normalizeTimeEntriesFiltersState('nope')).toEqual(createDefaultTimeEntriesFilters())
  })

  it('falls back to defaults for malformed JSON', () => {
    expect(parseStoredTimeEntriesFilters('{not json')).toEqual(createDefaultTimeEntriesFilters())
  })

  it('falls back to defaults for an unknown sort_by (hand-edited storage)', () => {
    const stored = JSON.stringify({ values: { user_id: '5' }, sortBy: 'hacked', sortDirection: 'desc' })
    expect(normalizeTimeEntriesFiltersState(JSON.parse(stored)).sortBy).toBe('date')
  })

  it('round-trips a valid stored payload', () => {
    const state: TimeEntriesFiltersState = {
      values: { user_id: '12', task_type_ids: ['1', '2'] },
      sortBy: 'target_minutes',
      sortDirection: 'desc',
    }
    expect(parseStoredTimeEntriesFilters(JSON.stringify(state))).toEqual(state)
  })
})

describe('countActiveTimeEntriesFilters', () => {
  it('counts only the 8 drawer filters, never period_preset', () => {
    const filters: TimeEntriesFiltersState = {
      values: {
        period_preset: ['week'],
        user_id: '5',
        task_type_ids: ['1', '2'],
        is_active: 'true',
      },
      sortBy: 'date',
      sortDirection: 'asc',
    }

    expect(countActiveTimeEntriesFilters(filters)).toBe(3)
  })

  it('ignores an empty array/blank string as "not active"', () => {
    const filters: TimeEntriesFiltersState = {
      values: { user_id: '', task_type_ids: [] },
      sortBy: 'date',
      sortDirection: 'asc',
    }

    expect(countActiveTimeEntriesFilters(filters)).toBe(0)
  })

  it('declares exactly the 8 filters of D-13 (Periodo excluded)', () => {
    expect(TIME_ENTRY_FILTER_DEFINITIONS.map((definition) => definition.key).sort()).toEqual(
      [
        'daily_statuses',
        'is_active',
        'opportunity_ids',
        'registry_ids',
        'task_ids',
        'task_type_ids',
        'user_id',
        'work_order_ids',
      ].sort(),
    )
  })
})

describe('removeTimeEntriesFilterValue', () => {
  it('removes exactly the given key, leaving a new object', () => {
    const values = { user_id: '5', task_type_ids: ['1'] }
    const next = removeTimeEntriesFilterValue(values, 'user_id')

    expect(next).toEqual({ task_type_ids: ['1'] })
    expect(values).toEqual({ user_id: '5', task_type_ids: ['1'] })
  })
})

describe('buildTimeEntriesFilterParams', () => {
  it('converts UI values into typed backend filters (D-3 e: arrays for multi-value)', () => {
    const filters: TimeEntriesFiltersState = {
      values: {
        user_id: '7',
        period_preset: [],
        date_from: '2026-09-14',
        date_to: '2026-09-20',
        task_type_ids: ['1', '2'],
        registry_ids: ['3'],
        daily_statuses: ['over_target', 'invalid_status'],
        is_active: 'true',
      },
      sortBy: 'date',
      sortDirection: 'asc',
    }

    expect(buildTimeEntriesFilterParams(filters)).toEqual({
      user_id: 7,
      period_preset: undefined,
      date_from: '2026-09-14',
      date_to: '2026-09-20',
      task_type_ids: [1, 2],
      registry_ids: [3],
      opportunity_ids: undefined,
      work_order_ids: undefined,
      task_ids: undefined,
      daily_statuses: ['over_target'],
      is_active: true,
    })
  })

  it('drops a non-positive/non-integer id and an unknown period preset', () => {
    const filters: TimeEntriesFiltersState = {
      values: { user_id: '-1', period_preset: ['quarter'], task_type_ids: ['0', 'abc'] },
      sortBy: 'date',
      sortDirection: 'asc',
    }
    const params = buildTimeEntriesFilterParams(filters)

    expect(params.user_id).toBeUndefined()
    expect(params.period_preset).toBeUndefined()
    expect(params.task_type_ids).toBeUndefined()
  })
})

describe('buildTimeEntriesListParams', () => {
  it('adds sort and pagination on top of the filter params', () => {
    const filters: TimeEntriesFiltersState = {
      values: {},
      sortBy: 'target_minutes',
      sortDirection: 'desc',
    }

    expect(buildTimeEntriesListParams(filters, 2, 15)).toMatchObject({
      sort_by: 'target_minutes',
      sort_direction: 'desc',
      page: 2,
      per_page: 15,
    })
  })

  it('falls back to the default sort_by for a value outside the allow-list', () => {
    const filters: TimeEntriesFiltersState = { values: {}, sortBy: 'hacked', sortDirection: 'asc' }

    expect(buildTimeEntriesListParams(filters, 1, 15).sort_by).toBe('date')
  })
})

describe('buildTimeEntriesFilterChips', () => {
  it('builds one chip per active filter, in definition order', () => {
    const filters: TimeEntriesFiltersState = {
      values: { is_active: 'true', daily_statuses: ['over_target'] },
      sortBy: 'date',
      sortDirection: 'asc',
    }

    const chips = buildTimeEntriesFilterChips(filters, translate)

    expect(chips.map((chip) => chip.key)).toEqual(['daily_statuses', 'is_active'])
    expect(chips[0].label).toBe('timeEntries.filters.fields.dailyStatus: timeEntries.dailyStatus.overTarget')
    expect(chips[1].label).toBe('timeEntries.filters.fields.isActive: timeEntries.filters.isActiveOptions.true')
  })

  it('lets the caller override id-backed label resolution', () => {
    const filters: TimeEntriesFiltersState = {
      values: { registry_ids: ['3'] },
      sortBy: 'date',
      sortDirection: 'asc',
    }

    const chips = buildTimeEntriesFilterChips(filters, translate, (_definition, rawValue) => `Acme #${rawValue}`)

    expect(chips[0].label).toBe('timeEntries.filters.fields.registry: Acme #3')
  })

  it('produces no chip when nothing is active', () => {
    expect(buildTimeEntriesFilterChips(createDefaultTimeEntriesFilters(), translate)).toEqual([])
  })
})
