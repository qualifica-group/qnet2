import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import { useQuoteAssignmentScope } from '@/features/request-management/use-quote-assignment-scope'
import { ENROLLEE_MODULE, RequestModuleProvider } from '@/features/request-management/request-module'

/**
 * Spec 0130 D-9: `POST /assignment/selection-scope` has no module segment of
 * its own, so the picker's competence/site lookup must send the RECORD
 * family discriminant `module.assignmentDomain` — `'quotes'` for Gestione
 * Richieste (default, unwrapped), `'enrollees'` for Gestione Iscritti.
 */

const fetchAssignmentScopeMock = vi.fn()
vi.mock('@/features/assignment/api', () => ({
  fetchAssignmentScope: (...args: unknown[]) => fetchAssignmentScopeMock(...args),
}))

function wrapper(module?: typeof ENROLLEE_MODULE) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      {module ? <RequestModuleProvider module={module}>{children}</RequestModuleProvider> : children}
    </QueryClientProvider>
  )
}

beforeEach(() => {
  fetchAssignmentScopeMock.mockReset()
  fetchAssignmentScopeMock.mockResolvedValue({
    product_category_ids: [],
    operational_site_id: null,
    campaign_ids: [],
    single_operator_available: true,
  })
})

describe('useQuoteAssignmentScope (spec 0130 D-9)', () => {
  it('sends domain: "quotes" by default (Gestione Richieste, unwrapped)', async () => {
    renderHook(() => useQuoteAssignmentScope([11, 22], true), { wrapper: wrapper() })

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'quotes', ids: [11, 22] }),
    )
  })

  it('sends domain: "enrollees" under ENROLLEE_MODULE', async () => {
    renderHook(() => useQuoteAssignmentScope([11, 22], true), { wrapper: wrapper(ENROLLEE_MODULE) })

    await waitFor(() =>
      expect(fetchAssignmentScopeMock).toHaveBeenCalledWith({ domain: 'enrollees', ids: [11, 22] }),
    )
  })
})
