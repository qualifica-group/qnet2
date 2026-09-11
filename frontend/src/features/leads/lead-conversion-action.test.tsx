import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadDetailScreen } from '@/features/leads/lead-screens'
import type { ModuleFormScreenMode, OpenMode } from '@/features/modules/types'
import type { LeadDetail } from '@/features/leads/types'

/**
 * Recovers the coverage of the deleted `pages/lead-detail-page.test.tsx`
 * (AC-078): the lead detail offers "Create opportunity" (gated
 * `opportunities.create`), or "Go to opportunity" when `lead.opportunity` is
 * set.
 *
 * That CTA used to live in `LeadDetailPageActions`, mounted ONLY by the generic
 * `ModuleDetailPage` — so the Sheet never offered the conversion at all. It now
 * lives in the record card's identity band (`LeadConversionAction`), which is
 * why these tests drive it through `LeadDetailScreen`: the same component both
 * surfaces mount, so the coverage no longer depends on which one is open.
 *
 * Spec 0045 (AC-022/023/025/026): "Create opportunity" opens the Opportunity
 * form through `useModuleOpener`, respecting the user's opportunities open mode
 * (modal Sheet vs dedicated page) instead of always navigating.
 */

const fetchLeadMock = vi.fn<(id: number) => Promise<LeadDetail>>()

vi.mock('@/features/leads/api', () => ({
  fetchLead: (id: number) => fetchLeadMock(id),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id] as const,
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// The opportunities Sheet's open mode varies per test (AC-022 modal, AC-023 page).
let opportunitiesOpenMode: OpenMode = 'modal'
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => opportunitiesOpenMode,
}))

const navigateMock = vi.fn()
vi.mock('react-router-dom', async () => {
  const actual = await vi.importActual<typeof import('react-router-dom')>('react-router-dom')
  return { ...actual, useNavigate: () => navigateMock }
})

// Isolates the real `useModuleOpener('opportunities')` call from the actual
// opportunities FormScreen (owned by another teammate, in flux): intercepts
// that exact file by its resolved path before the module registry's
// `import.meta.glob` eagerly imports it.
vi.mock('@/features/opportunities/opportunity-screens', () => ({
  moduleScreen: {
    domain: 'opportunities',
    basePath: '/opportunities',
    defaultMode: 'modal',
    labelKey: 'navigation.opportunities',
    DetailScreen: () => null,
    FormScreen: ({
      mode,
      onSuccess,
      onCancel,
    }: {
      mode: ModuleFormScreenMode
      onSuccess: (id: number) => void
      onCancel: () => void
    }) => (
      <div>
        <div>{`opportunity-form-${mode.type}`}</div>
        {mode.type === 'create' && (
          <div>{`opportunity-params:${JSON.stringify(mode.params ?? null)}`}</div>
        )}
        <button type="button" onClick={() => onSuccess(99)}>
          stub-save-opportunity
        </button>
        <button type="button" onClick={onCancel}>
          stub-cancel-opportunity
        </button>
      </div>
    ),
  },
}))

function lead(overrides: Partial<LeadDetail> = {}): LeadDetail {
  return {
    id: 9,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'associated',
    opportunity: null,
    operator_id: 7,
    operational_site_id: 3,
    permissions: {
      resource: { view: true, create: true, update: false, delete: false, export: false, import: false },
      fields: {},
      actions: {},
    },
    ...overrides,
  } as LeadDetail
}

/** A QueryClient per test, never per render (frontend.md §10). */
function renderActions() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <LeadDetailScreen id={9} />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchLeadMock.mockReset()
  canMock.mockReset()
  canMock.mockReturnValue(true)
  navigateMock.mockReset()
  opportunitiesOpenMode = 'modal'
})

describe('LeadConversionAction, in the lead record card', () => {
  it('AC-078: offers "Create opportunity" when the lead has none', async () => {
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderActions()

    expect(await screen.findByRole('button', { name: /create opportunity/i })).toBeInTheDocument()
  })

  it('AC-078: offers "Go to opportunity" to the existing opportunity when the lead has one', async () => {
    fetchLeadMock.mockResolvedValue(
      lead({ opportunity: { id: 42 } as LeadDetail['opportunity'] }),
    )

    renderActions()

    const link = await screen.findByRole('link', { name: /go to opportunity/i })
    expect(link).toHaveAttribute('href', '/opportunities/42')
  })

  it('AC-026: hides the "Create opportunity" action without the opportunities.create permission', async () => {
    canMock.mockReturnValue(false)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderActions()

    await waitFor(() => expect(fetchLeadMock).toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: /create opportunity/i })).not.toBeInTheDocument()
  })

  /** Directive 2026-07-21: Operator/Site are optional, so the former correction gate is gone — every lead opens the Opportunity form directly. */
  it('a lead missing Operator/Site opens the prefilled Opportunity form directly, no correction step', async () => {
    fetchLeadMock.mockResolvedValue(
      lead({ opportunity: null, operator_id: null, operational_site_id: null }),
    )

    renderActions()

    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))

    expect(await screen.findByText('opportunity-form-create')).toBeInTheDocument()
    expect(screen.getByText('opportunity-params:{"lead_id":9}')).toBeInTheDocument()
    expect(screen.queryByText('Complete the lead first')).not.toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('AC-022: a ready lead opens the Opportunity modal Sheet prefilled, no navigation', async () => {
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))
    renderActions()

    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))

    expect(await screen.findByText('opportunity-form-create')).toBeInTheDocument()
    expect(screen.getByText('opportunity-params:{"lead_id":9}')).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('AC-023: navigates to the Opportunity form deep-link when the resolved open mode is "page"', async () => {
    opportunitiesOpenMode = 'page'
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))
    renderActions()

    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))

    expect(navigateMock).toHaveBeenCalledWith('/opportunities/new?lead_id=9')
    expect(screen.queryByText('opportunity-form-create')).not.toBeInTheDocument()
  })

  it('AC-025: saving from the modal Sheet refreshes the detail so the button becomes "Go to opportunity"', async () => {
    fetchLeadMock.mockResolvedValueOnce(lead({ opportunity: null }))
    renderActions()

    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))
    expect(await screen.findByText('opportunity-form-create')).toBeInTheDocument()

    fetchLeadMock.mockResolvedValueOnce(
      lead({ opportunity: { id: 42 } as LeadDetail['opportunity'] }),
    )
    fireEvent.click(screen.getByText('stub-save-opportunity'))

    await waitFor(() => expect(fetchLeadMock).toHaveBeenCalledTimes(2))
    const link = await screen.findByRole('link', { name: /go to opportunity/i })
    expect(link).toHaveAttribute('href', '/opportunities/42')
    expect(screen.queryByText('opportunity-form-create')).not.toBeInTheDocument()
  })
})
