import { describe, expect, it } from 'vitest'
import { toDateServerSearch } from '@/components/data-table/date-filter-search'

// Task 427789: "Data inizio" on /work-orders, the user searches January 2026 as "01/2026".
describe('toDateServerSearch', () => {
  it.each([
    ['01/2026', '2026-01'],
    ['1/2026', '2026-01'],
    ['15/01/2026', '2026-01-15'],
    ['5.1.2026', '2026-01-05'],
    ['15/01', '-01-15'],
    [' 01/2026 ', '2026-01'],
  ])('rewrites the day-first search %s into %s', (search, expected) => {
    expect(toDateServerSearch(search, 'dmy')).toBe(expected)
  })

  it.each([
    ['01/15/2026', '2026-01-15'],
    ['01/15', '-01-15'],
  ])('rewrites the month-first search %s into %s', (search, expected) => {
    expect(toDateServerSearch(search, 'mdy')).toBe(expected)
  })

  it('rewrites the ISO search into the same text', () => {
    expect(toDateServerSearch('2026-01', 'ymd')).toBe('2026-01')
    expect(toDateServerSearch('2026/1/5', 'ymd')).toBe('2026-01-05')
  })

  it.each([
    ['2026', 'dmy'],
    ['15', 'dmy'],
    ['15/01/2026 10:30', 'dmy'],
    ['gennaio', 'dmy'],
    ['123/2026', 'dmy'],
    ['15/2026', 'mdy'],
  ] as const)('leaves %s unchanged when it is not a date piece of the %s pattern', (search, dateFormat) => {
    expect(toDateServerSearch(search, dateFormat)).toBe(search)
  })
})
