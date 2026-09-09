import { describe, expect, it, vi, beforeEach } from 'vitest'
import { renderHook } from '@testing-library/react'
import {
  fetchUsersForSelect,
  useUsersForSelect,
  USERS_FOR_SELECT_RESOURCE,
} from '@/features/users/for-select-api'

const fetchForSelect = vi.fn()
const useForSelect = vi.fn()

vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: (...args: unknown[]) => fetchForSelect(...args),
}))
vi.mock('@/features/for-select/use-for-select', () => ({
  useForSelect: (args: unknown) => useForSelect(args),
  useForSelectLabels: () => new Map(),
}))

beforeEach(() => {
  fetchForSelect.mockReset()
  useForSelect.mockReset()
})

describe('users for-select wrappers', () => {
  it('fetches against the users resource', async () => {
    fetchForSelect.mockResolvedValue({ items: [] })
    await fetchUsersForSelect({ search: 'jo' })
    expect(fetchForSelect).toHaveBeenCalledWith(USERS_FOR_SELECT_RESOURCE, {
      search: 'jo',
    })
  })

  it('binds the hook to the users resource', () => {
    useForSelect.mockReturnValue({})
    renderHook(() => useUsersForSelect({ search: 'a', ids: [1], enabled: true }))
    expect(useForSelect).toHaveBeenCalledWith({
      resource: USERS_FOR_SELECT_RESOURCE,
      search: 'a',
      ids: [1],
      enabled: true,
    })
  })

  it('forwards the competence filter (spec 0110) as an array param', () => {
    useForSelect.mockReturnValue({})
    renderHook(() =>
      useUsersForSelect({
        search: '',
        params: { operational_site_id: 4, competence_category_ids: [3, 7] },
      }),
    )
    expect(useForSelect).toHaveBeenCalledWith(
      expect.objectContaining({
        params: { operational_site_id: 4, competence_category_ids: [3, 7] },
      }),
    )
  })
})
