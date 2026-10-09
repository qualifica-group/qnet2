import { describe, expect, it } from 'vitest'
import {
  MAX_PROGRAM_GROUPS,
  addLinesToGroup,
  applyCommonToAll,
  automaticTitlePreview,
  automaticTitleProducts,
  groupDisplayTitle,
  createGroup,
  duplicateGroup,
  groupByCategory,
  groupOfLine,
  newGroupFromLines,
  oneGroupPerLine,
  removeGroup,
  removeLineFromGroup,
  resolveTargetLines,
} from '@/features/contracts/contract-program-groups'
import type { ContractProgramCommon, ContractProgramGroup } from '@/features/contracts/contract-program-schema'
import type { ContractProgrammableLine } from '@/features/contracts/types'

function line(id: number, name: string, categoryId: number | null, occupied = false): ContractProgrammableLine {
  return {
    id,
    sort_order: id,
    product: {
      id,
      code: `P-${id}`,
      name,
      category: categoryId === null ? null : { id: categoryId, name: `Cat ${categoryId}` },
    },
    quantity: '1.00',
    unit_of_measure: null,
    work_order: occupied ? { id: 99, code: 'COM-0099' } : null,
  }
}

const LINES = [
  line(1, 'A', 5),
  line(2, 'B', 5),
  line(3, 'C', 6),
  line(4, 'A', null),
  line(5, 'D', null),
  line(6, 'Taken', 5, true),
]

const COMMON: ContractProgramCommon = {
  type: 'project',
  start_date: '2026-03-01',
  supervisor_ids: [21],
  task_template_id: 3,
}

const ids = (groups: ContractProgramGroup[]) => groups.map((group) => group.quote_line_ids)

describe('newGroupFromLines (AC-020, AC-023)', () => {
  it('creates a group holding the lines and inheriting the common values', () => {
    const [group] = newGroupFromLines([], COMMON, [1, 2, 3])

    expect(group).toMatchObject({ ...COMMON, title: '', quote_line_ids: [1, 2, 3] })
    expect(group.key).toBeTruthy()
  })

  it('moves lines out of their previous group, dropping the group it empties (AC-021, D-6)', () => {
    const first = newGroupFromLines([], COMMON, [1, 2])

    expect(ids(newGroupFromLines(first, COMMON, [2]))).toEqual([[1], [2]])
    expect(ids(newGroupFromLines(first, COMMON, [1, 2]))).toEqual([[1, 2]])
  })

  it('refuses to exceed the cap, returning the same array (D-9)', () => {
    const full = Array.from({ length: MAX_PROGRAM_GROUPS }, () => createGroup(COMMON))

    expect(newGroupFromLines(full, COMMON, [])).toBe(full)
  })
})

describe('addLinesToGroup / removeLineFromGroup / removeGroup (AC-021)', () => {
  const base = [createGroup(COMMON, [1, 2, 3]), createGroup(COMMON, [4])]

  it('adds lines to the target group', () => {
    expect(ids(addLinesToGroup(base, 1, [5]))).toEqual([[1, 2, 3], [4, 5]])
  })

  it('moves a line from another group, which keeps its remaining lines', () => {
    expect(ids(addLinesToGroup(base, 1, [3]))).toEqual([[1, 2], [4, 3]])
  })

  it('frees a line removed from its group', () => {
    const next = removeLineFromGroup(base, 0, 2)

    expect(ids(next)).toEqual([[1, 3], [4]])
    expect(groupOfLine(next, 2)).toBeNull()
  })

  it('frees every line of a deleted group', () => {
    const next = removeGroup(base, 0)

    expect(ids(next)).toEqual([[4]])
    expect(groupOfLine(next, 1)).toBeNull()
    expect(groupOfLine(next, 4)).toBe(0)
  })
})

describe('resolveTargetLines (D-8)', () => {
  it('uses the selected programmable lines in offer order, ignoring occupied ones', () => {
    expect(resolveTargetLines(LINES, [], [5, 1, 6]).map((l) => l.id)).toEqual([1, 5])
  })

  it('falls back to every free line, excluding those already grouped and occupied ones', () => {
    const groups = [createGroup(COMMON, [1])]

    expect(resolveTargetLines(LINES, groups, []).map((l) => l.id)).toEqual([2, 3, 4, 5])
  })
})

describe('oneGroupPerLine / groupByCategory (AC-022)', () => {
  it('creates one single-line group per selected line', () => {
    const targets = resolveTargetLines(LINES, [], [1, 2, 3])

    expect(ids(oneGroupPerLine([], COMMON, targets))).toEqual([[1], [2], [3]])
  })

  it('creates one group per category plus one for lines without category', () => {
    const targets = resolveTargetLines(LINES, [], [])

    expect(ids(groupByCategory([], COMMON, targets))).toEqual([[1, 2], [3], [4, 5]])
  })

  it('does nothing without target lines and refuses past the cap', () => {
    const groups = [createGroup(COMMON, [1])]
    const full = Array.from({ length: MAX_PROGRAM_GROUPS }, () => createGroup(COMMON))

    expect(oneGroupPerLine(groups, COMMON, [])).toBe(groups)
    expect(groupByCategory(full, COMMON, resolveTargetLines(LINES, full, []))).toBe(full)
  })
})

describe('common values (AC-023, D-7)', () => {
  it('applyCommonToAll overwrites the four fields and keeps titles and lines', () => {
    const groups = [{ ...createGroup({ ...COMMON, type: 'processing', start_date: '' }, [1]), title: 'Mine' }]

    const [group] = applyCommonToAll(groups, COMMON)

    expect(group).toMatchObject({ ...COMMON, title: 'Mine', quote_line_ids: [1], key: groups[0].key })
  })

  it('duplicateGroup copies the four fields, blank title, no lines, right after the source', () => {
    const source = { ...createGroup(COMMON, [1]), title: 'Mine' }
    const next = duplicateGroup([source, createGroup(COMMON, [2])], 0)

    expect(next).toHaveLength(3)
    expect(next[1]).toMatchObject({ ...COMMON, title: '', quote_line_ids: [] })
    expect(next[1].key).not.toBe(source.key)
    expect(ids(next)).toEqual([[1], [], [2]])
  })
})

describe('automaticTitleProducts (AC-024)', () => {
  it('lists the distinct product names in offer order, not selection order', () => {
    const group = createGroup(COMMON, [5, 4, 1])

    expect(automaticTitleProducts(group, LINES)).toEqual(['A', 'D'])
  })
})

describe('automaticTitlePreview / groupDisplayTitle (AC-024)', () => {
  it('previews the code stand-in followed by the products joined by " + "', () => {
    expect(automaticTitlePreview(['A', 'D'])).toBe('COM-… - A + D')
    expect(automaticTitlePreview([])).toBe('COM-…')
  })

  it('prefers the typed title over the preview', () => {
    const group = createGroup(COMMON, [1, 2])

    expect(groupDisplayTitle(group, LINES)).toBe('COM-… - A + B')
    expect(groupDisplayTitle({ ...group, title: ' Mine ' }, LINES)).toBe('Mine')
  })
})
