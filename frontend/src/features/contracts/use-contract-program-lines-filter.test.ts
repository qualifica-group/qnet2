import { describe, expect, it } from 'vitest'
import {
  ALL_CATEGORIES,
  NO_CATEGORY,
  filterProgramLines,
  programLineCategories,
} from '@/features/contracts/use-contract-program-lines-filter'
import type { ContractProgrammableLine } from '@/features/contracts/types'

function line(id: number, code: string, name: string, category: { id: number; name: string } | null): ContractProgrammableLine {
  return {
    id,
    sort_order: id,
    product: { id, code, name, category },
    quantity: '1.00',
    unit_of_measure: null,
    work_order: null,
  }
}

const ISO = { id: 3, name: 'Consulenza ISO' }
const COURSES = { id: 1, name: 'Formazione' }

const LINES = [
  line(1, 'PRD-0890', 'Attività di consulenza EN ISO 39001:2012 _ 1° Anno', ISO),
  line(2, 'PRD-0891', 'Attività di consulenza EN ISO 39001:2012 _ 2° Anno', ISO),
  line(3, 'PRD-0700', 'Attività di consulenza EN ISO 9001:2015 _ 1° Anno', ISO),
  line(4, 'PRD-0100', 'Corso BIM manager', COURSES),
  line(5, 'PRD-0200', 'Spese di trasferta', null),
]

const ids = (lines: ContractProgrammableLine[]) => lines.map((item) => item.id)

describe('filterProgramLines', () => {
  it('returns every line with an empty search and every category', () => {
    expect(ids(filterProgramLines(LINES, '  ', ALL_CATEGORIES))).toEqual([1, 2, 3, 4, 5])
  })

  it('matches every word of the search, ignoring case and accents, keeping the original order', () => {
    expect(ids(filterProgramLines(LINES, 'attivita 39001', ALL_CATEGORIES))).toEqual([1, 2])
    expect(ids(filterProgramLines(LINES, '39001 2° anno', ALL_CATEGORIES))).toEqual([2])
  })

  it('matches the product code too', () => {
    expect(ids(filterProgramLines(LINES, 'prd-0100', ALL_CATEGORIES))).toEqual([4])
  })

  it('narrows to one category, or to the lines without one', () => {
    expect(ids(filterProgramLines(LINES, '', String(COURSES.id)))).toEqual([4])
    expect(ids(filterProgramLines(LINES, '', NO_CATEGORY))).toEqual([5])
  })

  it('combines search and category', () => {
    expect(ids(filterProgramLines(LINES, '1° anno', String(ISO.id)))).toEqual([1, 3])
    expect(ids(filterProgramLines(LINES, 'BIM', String(ISO.id)))).toEqual([])
  })
})

describe('programLineCategories', () => {
  it('lists each category once, by name, without the uncategorized lines', () => {
    expect(programLineCategories(LINES)).toEqual([
      { value: '3', name: 'Consulenza ISO' },
      { value: '1', name: 'Formazione' },
    ])
  })
})
