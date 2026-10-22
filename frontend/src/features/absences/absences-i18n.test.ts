import { describe, expect, it } from 'vitest'
import { en } from '@/i18n/locales/en'
import { it as itLocale } from '@/i18n/locales/it'
import { ABSENCE_REQUEST_STATUSES } from '@/features/absences/types'

/** Column ids of the backend request grids (`AbstractAbsenceRequestsTableDefinition::columns()`). */
const GRID_COLUMNS = [
  'user_name',
  'absence_type_name',
  'status',
  'starts_on',
  'ends_on',
  'is_full_day',
  'minutes',
  'submitted_at',
  'decided_by_name',
  'day_part',
  'is_short_notice',
] as const

describe.each([
  ['en', en],
  ['it', itLocale],
])('absence request grids i18n (%s)', (_locale, resources) => {
  it('labels every grid column under absences.requests.columns', () => {
    expect(Object.keys(resources.absences.requests.columns).sort()).toEqual([...GRID_COLUMNS].sort())
  })

  it('labels every status badge under enums.absence_request_status', () => {
    expect(Object.keys(resources.enums.absence_request_status).sort()).toEqual([...ABSENCE_REQUEST_STATUSES].sort())
  })
})
