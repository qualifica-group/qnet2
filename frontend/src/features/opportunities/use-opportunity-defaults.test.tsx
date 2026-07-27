import { describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useOpportunityDefaults } from '@/features/opportunities/use-opportunity-defaults'
import type { OpportunityDefaults } from '@/features/opportunities/types'

/** AC-075: the create-from-lead defaults query is gated by a valid `lead_id`, never fired for a plain manual create. */

const fetchOpportunityDefaultsMock = vi.fn<() => Promise<OpportunityDefaults>>()
vi.mock('@/features/opportunities/opportunity-defaults-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/opportunity-defaults-api')>(
    '@/features/opportunities/opportunity-defaults-api',
  )
  return {
    ...actual,
    fetchOpportunityDefaults: () => fetchOpportunityDefaultsMock(),
  }
})

function defaults(overrides: Partial<OpportunityDefaults> = {}): OpportunityDefaults {
  return {
    lead_id: 9,
    existing_opportunity_id: null,
    values: {
      // spec 0041 D-3/AC-050: no longer a derived field.
      referent_id: null,
      source_id: 20,
      registry_id: 30,
      operational_site_id: null,
      general_notes: null,
    },
    references: {
      source: { id: 20, name: 'Web' },
      registry: { id: 30, name: 'Acme S.p.A.' },
      operational_site: null,
    },
    locked_fields: ['source_id', 'registry_id'],
    // Amendment rev.3 (AC-102/103): 0/1 seed row, editable/removable, never locked.
    product_lines: [
      {
        id: 900,
        business_function: { id: 40, name: 'Sales' },
        product_category: { id: 50, name: 'Consulting' },
      },
    ],
    // Directive 2026-07-21: the lead's Operator prefills the first "Gestore
    // Account" slot; not under test in this file (this hook only reads/caches
    // the defaults query; the prefill decision is `use-opportunity-lead-selection.ts`'s).
    manager_slots: [],
    manager_refs: [],
    ...overrides,
  }
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('useOpportunityDefaults', () => {
  it('does not fetch when leadId is null', () => {
    const { result } = renderHook(() => useOpportunityDefaults(null), { wrapper: wrapper() })

    expect(fetchOpportunityDefaultsMock).not.toHaveBeenCalled()
    expect(result.current.data).toBeUndefined()
  })

  it('fetches and resolves the defaults for a given leadId', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults())

    const { result } = renderHook(() => useOpportunityDefaults(9), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.data).toBeDefined())
    expect(result.current.data?.values.registry_id).toBe(30)
    expect(result.current.data?.locked_fields).toContain('registry_id')
    expect(fetchOpportunityDefaultsMock).toHaveBeenCalledTimes(1)
  })

  it('surfaces existing_opportunity_id when the lead is already linked (D-2)', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults({ existing_opportunity_id: 77 }))

    const { result } = renderHook(() => useOpportunityDefaults(9), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.data?.existing_opportunity_id).toBe(77))
  })
})
