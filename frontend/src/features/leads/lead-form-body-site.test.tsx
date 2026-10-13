import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  CAMPAIGN_PREFILL,
  SITE_A,
  clickCreateSave,
  confirmOpenRow,
  leadFixture,
  leadWrapper,
  pencilOf,
  permissionsFor,
  pickInRow,
  stubState,
} from '@/features/leads/lead-test-fixtures'

/**
 * Project -> campaign -> lead prefill chain (spec 0176 D-2): a picked
 * Campaign carrying its own Sede and Fonte prefills both, still editable.
 * REQUIREMENT CHANGED (spec 0217): the rows are closed, so the prefill is read
 * by opening the prefilled row (create) or in the PATCH the Campaign row
 * sends (detail, D-3: the cascade rides on the same save).
 */

const createLeadMock = vi.fn()
const updateLeadMock = vi.fn()

vi.mock('@/features/leads/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/leads/api')>()),
  createLead: (...args: unknown[]) => createLeadMock(...args),
  updateLead: (...args: unknown[]) => updateLeadMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/leads/lead-test-stubs')).StubSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/leads/lead-test-stubs')).StubMultiSelect,
}))

function renderCreate() {
  const { wrapper } = leadWrapper()
  render(<LeadForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  stubState.campaigns = [CAMPAIGN_PREFILL]
})

describe('LeadForm — Sede and Fonte prefill from Campaign', () => {
  it('prefills the Site and Source rows from the picked Campaign meta, the Site still editable', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Campaign' })

    pickInRow('Campaign', 'Campaign')

    fireEvent.click(pencilOf('Source'))
    expect(screen.getByTestId('select-Source')).toHaveTextContent('77')
    confirmOpenRow('Done')

    fireEvent.click(pencilOf('Site'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('55')
    // The prefill is not a lock: the user can still override it manually.
    fireEvent.click(screen.getByTestId('select-Site'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent(String(SITE_A.id))
  })

  it('sends the campaign-prefilled operational_site_id and source_id in the create payload', async () => {
    createLeadMock.mockResolvedValue(leadFixture())
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Registry' })

    pickInRow('Registry', 'Registry')
    pickInRow('Campaign', 'Campaign')
    clickCreateSave()

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0]).toMatchObject({ operational_site_id: 55, source_id: 77 })
  })

  it('carries the prefill in the same PATCH as the Campaign change on the detail (spec 0217 D-3)', async () => {
    updateLeadMock.mockResolvedValue(leadFixture())
    const { wrapper } = leadWrapper()
    render(<LeadDetailView lead={leadFixture()} />, { wrapper })

    pickInRow('Campaign', 'Campaign', 'Save')

    await waitFor(() =>
      expect(updateLeadMock).toHaveBeenCalledWith(9, { campaign_id: 21, operational_site_id: 55, source_id: 77 }),
    )
  })

  it('a different single-select field never triggers the prefill', async () => {
    updateLeadMock.mockResolvedValue(leadFixture())
    const { wrapper } = leadWrapper()
    render(
      <LeadDetailView lead={leadFixture({ operational_site_id: 8, operational_site: { id: 8, label: 'Depot B' } })} />,
      { wrapper },
    )

    pickInRow('Source', 'Source', 'Save')

    await waitFor(() => expect(updateLeadMock).toHaveBeenCalledWith(9, { source_id: 3 }))
  })
})
