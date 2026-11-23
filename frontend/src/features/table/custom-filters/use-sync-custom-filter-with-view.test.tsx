import type { ReactNode } from 'react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useCustomFilterState, type CustomFilterState } from '@/features/table/custom-filters/use-custom-filter-state'
import { sameRules, useSyncCustomFilterWithView } from '@/features/table/custom-filters/use-sync-custom-filter-with-view'
import type { FilterRules, TableFilterView } from '@/features/table/types'

const listFilterViews = vi.fn()

vi.mock('@/features/table/filter-views-api', () => ({
  listFilterViews: (...args: unknown[]) => listFilterViews(...args),
}))

const CREATED_RULES: FilterRules = { and: [{ field: 'next_callback_at', operator: 'lte', value: '2026-11-10' }], or: [] }
const SAVED_RULES: FilterRules = { and: [{ field: 'next_callback_at', operator: 'lte', value: '2026-11-17' }], or: [] }

function savedView(rules: FilterRules): TableFilterView {
  return {
    id: 21,
    name: 'Richiami Giornalieri',
    filters: {},
    advanced_filters: {},
    visibility: 'private',
    owned: true,
    owner_name: null,
    rules,
    is_favorite: false,
  }
}

function renderSync(initial: CustomFilterState | null) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  const refreshGrid = vi.fn()
  const wrapper = ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
  const { result } = renderHook(
    () => {
      const active = useCustomFilterState(initial)
      useSyncCustomFilterWithView({ domain: 'request-management', active, refreshGrid })
      return active
    },
    { wrapper },
  )
  return { result, refreshGrid }
}

beforeEach(() => {
  listFilterViews.mockReset()
})

describe('useSyncCustomFilterWithView', () => {
  it('replaces a stale browser copy of a saved view with the rules saved since, and reloads the grid', async () => {
    listFilterViews.mockResolvedValue([savedView(SAVED_RULES)])
    const { result, refreshGrid } = renderSync({ rules: CREATED_RULES, viewId: 21, name: 'Richiami Giornalieri' })

    await waitFor(() => expect(result.current.state?.rules).toEqual(SAVED_RULES))
    expect(result.current.state?.viewId).toBe(21)
    expect(result.current.getActive()).toEqual(SAVED_RULES)
    expect(refreshGrid).toHaveBeenCalledTimes(1)
  })

  it('leaves a filter already matching its saved view untouched', async () => {
    listFilterViews.mockResolvedValue([savedView(SAVED_RULES)])
    const { result, refreshGrid } = renderSync({ rules: SAVED_RULES, viewId: 21, name: 'Richiami Giornalieri' })

    await waitFor(() => expect(listFilterViews).toHaveBeenCalled())
    expect(result.current.state?.rules).toEqual(SAVED_RULES)
    expect(refreshGrid).not.toHaveBeenCalled()
  })

  it('never loads the views for an ad-hoc filter (no saved view behind it)', () => {
    const { result, refreshGrid } = renderSync({ rules: CREATED_RULES })

    expect(listFilterViews).not.toHaveBeenCalled()
    expect(result.current.state?.rules).toEqual(CREATED_RULES)
    expect(refreshGrid).not.toHaveBeenCalled()
  })
})

describe('sameRules', () => {
  it('ignores the key order the server stores a rule set in', () => {
    const fromServer = JSON.parse('{"or":[],"and":[{"value":"2026-11-17","operator":"lte","field":"next_callback_at"}]}') as FilterRules

    expect(sameRules(fromServer, SAVED_RULES)).toBe(true)
    expect(sameRules(CREATED_RULES, SAVED_RULES)).toBe(false)
  })
})
