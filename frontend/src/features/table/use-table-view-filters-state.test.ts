import { describe, expect, it } from 'vitest'
import { searchPlaceholderColumnIds } from '@/features/table/use-table-view-filters-state'

describe('searchPlaceholderColumnIds', () => {
  it('leaves the legacy id out of the search placeholder, keeping the other columns in order', () => {
    expect(searchPlaceholderColumnIds(['name', 'email', 'old_id'])).toEqual(['name', 'email'])
  })

  it('keeps a list without the legacy id unchanged', () => {
    expect(searchPlaceholderColumnIds(['name'])).toEqual(['name'])
  })
})
