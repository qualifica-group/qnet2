import { describe, expect, it } from 'vitest'
import type { ColumnStatusWorkflow } from '@/features/request-management/column-statuses-api'
import { filterWorkflows, selectionOf, withStatuses } from '@/features/request-management/column-statuses-selection'

/** Spec 0391: the pure helpers of the "Colonne e stati" configurator. */

const WORKFLOWS: ColumnStatusWorkflow[] = [
  {
    id: null,
    name: 'Stati predefiniti',
    is_active: true,
    categories: [],
    statuses: [{ id: 1, name: 'Aperta', group: 'open', color: null }],
  },
  {
    id: 20,
    name: 'Orientamento specialistico',
    is_active: true,
    categories: ['Orientamento Specialistico'],
    statuses: [
      { id: 366, name: 'Da convocare', group: 'validated', color: null },
      { id: 371, name: 'Fine pratica', group: 'closed_won', color: null },
    ],
  },
  {
    id: 23,
    name: 'Finanza Agevolata',
    is_active: true,
    categories: ['Bandi'],
    statuses: [{ id: 500, name: 'In trattativa', group: 'pending', color: null }],
  },
]

describe('selectionOf', () => {
  it('keys the saved status ids by column, an absent column being empty', () => {
    const selection = selectionOf({
      columns: [{ key: 'associati', twin_key: null, status_ids: [371] }],
      workflows: WORKFLOWS,
    })

    expect(selection.associati).toEqual([371])
    expect(selection.unhandled_new_contacts).toEqual([])
    expect(Object.keys(selection)).toHaveLength(5)
  })
})

describe('withStatuses', () => {
  it('adds and removes ids keeping the list ascending and without repeats', () => {
    expect(withStatuses([5, 1], [3, 5], true)).toEqual([1, 3, 5])
    expect(withStatuses([1, 3, 5], [3, 9], false)).toEqual([1, 5])
  })
})

describe('filterWorkflows', () => {
  it('returns every set for an empty search', () => {
    expect(filterWorkflows(WORKFLOWS, '  ')).toBe(WORKFLOWS)
  })

  it('keeps a whole set when its name or one of its categories matches', () => {
    expect(filterWorkflows(WORKFLOWS, 'orientamento').map((workflow) => workflow.id)).toEqual([20])
    expect(filterWorkflows(WORKFLOWS, 'bandi')[0].statuses).toHaveLength(1)
  })

  it('keeps only the matching statuses otherwise, dropping the sets left empty', () => {
    const filtered = filterWorkflows(WORKFLOWS, 'fine')

    expect(filtered).toHaveLength(1)
    expect(filtered[0].statuses.map((status) => status.name)).toEqual(['Fine pratica'])
  })
})
