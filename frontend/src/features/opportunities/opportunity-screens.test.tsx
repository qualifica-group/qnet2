import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { OpportunityFormScreen } from '@/features/opportunities/opportunity-screens'
import ModuleFormPage from '@/features/modules/module-form-page'
import type { ModuleFormScreenMode } from '@/features/modules/types'
import type { OpportunityDefaults } from '@/features/opportunities/types'

/**
 * Spec 0045 AC-010/AC-011/AC-012/AC-013: the create branch of
 * `OpportunityFormScreen` must read `lead_id` from `mode.params` (the sole
 * channel, regardless of modal vs. deep-link caller), never from
 * `useSearchParams()`. Downstream (`useOpportunityCreateMode` ->
 * `useOpportunityDefaults`) is exercised for real; only the defaults HTTP
 * call and the terminal `OpportunityForm` are stubbed, since their own
 * behaviour is covered by `use-opportunity-defaults.test.tsx` and is out of
 * this adapter's scope.
 */

const fetchOpportunityDefaultsMock = vi.fn<(leadId: number) => Promise<OpportunityDefaults>>()
vi.mock('@/features/opportunities/opportunity-defaults-api', async () => {
  const actual = await vi.importActual<typeof import('@/features/opportunities/opportunity-defaults-api')>(
    '@/features/opportunities/opportunity-defaults-api',
  )
  return {
    ...actual,
    fetchOpportunityDefaults: (leadId: number) => fetchOpportunityDefaultsMock(leadId),
  }
})

// The deep-link regression test below mounts the real `ModuleFormPage`, which
// looks up its registry entry via `getModuleRegistryEntry`. The registry
// itself is an `import.meta.glob` over every `features/*/*-screens.tsx`
// (spec 0042) — coupling this test to it would make it depend on modules
// entirely outside this adapter's scope (and, right now, on `features/leads/`
// mid-edit by another teammate). Stub the lookup to return an entry built
// from the REAL `OpportunityFormScreen` (imported above, not re-declared),
// so the relay under test — query string -> ModuleFormPage -> mode.params ->
// OpportunityFormScreen -> parseEntityId -> useOpportunityCreateMode -> ...
// -- runs for real; only the module *lookup* mechanism is faked.
vi.mock('@/features/modules/module-registry', () => ({
  getModuleRegistryEntry: (domain: string) =>
    domain === 'opportunities'
      ? {
          domain: 'opportunities',
          basePath: '/opportunities',
          defaultMode: 'page',
          labelKey: 'navigation.opportunities',
          DetailScreen: () => null,
          FormScreen: OpportunityFormScreen,
        }
      : undefined,
}))

// `ModuleFormPage` gates on the module's `.create` ability. The permission
// check itself is covered by spec 0042; here it would only add an abilities
// fetch unrelated to the params relay under test.
vi.mock('@/features/auth/can', () => ({
  Can: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

// `ModuleFormPage`'s header renders the app breadcrumbs, which reach for the
// auth context. Page chrome is irrelevant to the query-string relay under test.
vi.mock('@/components/page-header', () => ({
  PageHeader: () => null,
}))

vi.mock('@/features/opportunities/opportunity-form', () => ({
  OpportunityForm: ({ mode }: { mode: { type: string; fromLead?: { leadId: number } } }) => (
    <p>form ready, lead {mode.fromLead ? mode.fromLead.leadId : 'none'}</p>
  ),
  OpportunityFormSkeleton: () => <p>loading</p>,
}))

function defaults(leadId: number): OpportunityDefaults {
  return {
    lead_id: leadId,
    existing_opportunity_id: null,
    values: {
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
    product_lines: [],
    manager_slots: [],
    manager_refs: [],
  }
}

beforeEach(() => {
  fetchOpportunityDefaultsMock.mockReset()
})

function renderScreen(mode: ModuleFormScreenMode) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <OpportunityFormScreen mode={mode} onSuccess={vi.fn()} onCancel={vi.fn()} />
    </QueryClientProvider>,
  )
}

describe('OpportunityFormScreen create adapter (spec 0045)', () => {
  it('AC-010: reads a numeric lead_id from mode.params (modal caller)', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults(7))

    renderScreen({ type: 'create', params: { lead_id: 7 } })

    await waitFor(() => expect(fetchOpportunityDefaultsMock).toHaveBeenCalledWith(7))
    expect(await screen.findByText('form ready, lead 7')).toBeInTheDocument()
  })

  it('AC-011: reads a string lead_id from mode.params identically (query-string caller)', async () => {
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults(7))

    renderScreen({ type: 'create', params: { lead_id: '7' } })

    await waitFor(() => expect(fetchOpportunityDefaultsMock).toHaveBeenCalledWith(7))
    expect(await screen.findByText('form ready, lead 7')).toBeInTheDocument()
  })

  it('AC-012: with no params, opens a free create and never calls the defaults endpoint', async () => {
    renderScreen({ type: 'create' })

    expect(await screen.findByText('form ready, lead none')).toBeInTheDocument()
    expect(fetchOpportunityDefaultsMock).not.toHaveBeenCalled()
  })

  it('AC-013: the /opportunities/new?lead_id=7 deep-link still prefills, end to end (regression)', async () => {
    // Starts from the ROUTE, not from a hand-built `mode`: the assertion only
    // holds if the whole relay survives — query string -> ModuleFormPage ->
    // mode.params -> OpportunityFormScreen -> parseEntityId. Asserting the
    // `mode` shape directly (as AC-011 does) cannot catch a break in that
    // relay, and this deep-link already ships (spec 0044), so a regression
    // here breaks users rather than an unreleased feature.
    fetchOpportunityDefaultsMock.mockResolvedValue(defaults(7))
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    render(
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={['/opportunities/new?lead_id=7']}>
          <Routes>
            <Route path="/opportunities/new" element={<ModuleFormPage domain="opportunities" />} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>,
    )

    await waitFor(() => expect(fetchOpportunityDefaultsMock).toHaveBeenCalledWith(7))
    expect(await screen.findByText('form ready, lead 7')).toBeInTheDocument()
  })
})
