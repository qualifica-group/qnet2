import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { toast } from 'sonner'
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
 * Spec 0140 (supersedes spec 0045 AC-022/023/025): "Create opportunity" no
 * longer opens the prefilled Opportunity form — it converts the lead directly
 * through the conversion endpoint. AC-026 (permission gate) is unchanged.
 */

const fetchLeadMock = vi.fn<(id: number) => Promise<LeadDetail>>()
const convertLeadsToOpportunitiesMock = vi.fn()

vi.mock('@/features/leads/api', () => ({
  fetchLead: (id: number) => fetchLeadMock(id),
  convertLeadsToOpportunities: (...args: unknown[]) => convertLeadsToOpportunitiesMock(...args),
  leadDetailQueryKey: (id: number | null) => ['leads', 'detail', id] as const,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// The opportunities Sheet's open mode varies per test ("Go to opportunity" stays a modal even in page mode).
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
  convertLeadsToOpportunitiesMock.mockReset()
  vi.mocked(toast.error).mockClear()
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

  it('opens the existing opportunity in a modal, even in page mode, instead of leaving the lead', async () => {
    opportunitiesOpenMode = 'page'
    fetchLeadMock.mockResolvedValue(
      lead({ opportunity: { id: 42 } as LeadDetail['opportunity'] }),
    )

    renderActions()

    fireEvent.click(await screen.findByRole('link', { name: /go to opportunity/i }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('AC-026: hides the "Create opportunity" action without the opportunities.create permission', async () => {
    canMock.mockReturnValue(false)
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))

    renderActions()

    await waitFor(() => expect(fetchLeadMock).toHaveBeenCalled())
    expect(screen.queryByRole('button', { name: /create opportunity/i })).not.toBeInTheDocument()
  })

  it('spec 0140: converts the lead directly, then the button becomes "Go to opportunity", no form', async () => {
    fetchLeadMock.mockResolvedValueOnce(lead({ opportunity: null, operator_id: null, operational_site_id: null }))
    convertLeadsToOpportunitiesMock.mockResolvedValue({ converted: 1, opportunity_ids: [42] })
    renderActions()

    fetchLeadMock.mockResolvedValueOnce(lead({ opportunity: { id: 42 } as LeadDetail['opportunity'] }))
    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))

    await waitFor(() => expect(convertLeadsToOpportunitiesMock).toHaveBeenCalledWith({ lead_ids: [9] }))
    const link = await screen.findByRole('link', { name: /go to opportunity/i })
    expect(link).toHaveAttribute('href', '/opportunities/42')
    expect(screen.queryByText('opportunity-form-create')).not.toBeInTheDocument()
    expect(navigateMock).not.toHaveBeenCalled()
  })

  it('spec 0140: a refused conversion toasts the reason and keeps "Create opportunity"', async () => {
    fetchLeadMock.mockResolvedValue(lead({ opportunity: null }))
    convertLeadsToOpportunitiesMock.mockRejectedValue(
      new AxiosError('failed', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'refused', errors: { reason: 'not_convertible', blockers: [{ id: 9, reason: 'not_derivable' }] } },
      } as never),
    )
    renderActions()

    fireEvent.click(await screen.findByRole('button', { name: /create opportunity/i }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(
        'The lead cannot be converted: its campaign has no business function or product category.',
      ),
    )
    expect(screen.getByRole('button', { name: /create opportunity/i })).toBeEnabled()
    expect(fetchLeadMock).toHaveBeenCalledTimes(1)
  })
})
