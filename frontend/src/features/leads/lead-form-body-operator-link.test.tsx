import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import { LeadDetailView } from '@/features/leads/lead-detail'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  OPERATOR_NO_SITE,
  OPERATOR_WITH_SITE,
  SITE_A,
  SITE_B,
  clickCreateSave,
  confirmOpenRow,
  leadFixture,
  leadWrapper,
  pencilOf,
  permissionsFor,
  pickInRow,
} from '@/features/leads/lead-test-fixtures'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/**
 * Sede <-> Operatore, reciprocally linked (spec 0048 AC-060..063, spec 0103
 * AC-026/027): the Sede scopes the Operatore list, an Operatore fills an
 * EMPTY Sede, a real Sede change clears the Operatore. REQUIREMENT CHANGED
 * (spec 0217): the rows are closed, so a linked value is read by opening its
 * row (create) or in the PATCH of the row that changed it (detail, D-3).
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

const FILTER_HINT = 'The operators shown are limited to those of the chosen Site.'

function renderCreate() {
  const { wrapper } = leadWrapper()
  render(<LeadForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

function renderDetail(lead: LeadDetailWithPermissions) {
  const { wrapper } = leadWrapper()
  render(<LeadDetailView lead={lead} />, { wrapper })
}

/** A lead already placed in SITE_A, with OPERATOR_NO_SITE assigned. */
const PLACED_LEAD = leadFixture({
  operational_site_id: SITE_A.id,
  operational_site: { id: SITE_A.id, label: SITE_A.label },
  operator_id: OPERATOR_NO_SITE.id,
  operator: { id: OPERATOR_NO_SITE.id, name: OPERATOR_NO_SITE.label },
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  updateLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('Lead — Sede filters the Operatore picker (AC-060)', () => {
  it('leaves the Operator unfiltered, without the hint, while no Sede is chosen', async () => {
    renderCreate()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Operator' }))
    expect(screen.getByTestId('select-Operator')).toHaveAttribute('data-params', '')
    expect(screen.queryByText(FILTER_HINT)).not.toBeInTheDocument()
  })

  it('scopes the Operator picker to the chosen Sede, with the hint', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Site' })

    pickInRow('Site', 'Site')
    fireEvent.click(pencilOf('Operator'))

    expect(screen.getByTestId('select-Operator')).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_A.id }),
    )
    expect(screen.getByText(FILTER_HINT)).toBeInTheDocument()
  })

  it('scopes the detail Operator picker to the lead Sede (AC-063)', () => {
    renderDetail(PLACED_LEAD)

    fireEvent.click(pencilOf('Operator'))
    expect(screen.getByTestId('select-Operator')).toHaveAttribute(
      'data-params',
      JSON.stringify({ operational_site_id: SITE_A.id }),
    )
  })
})

describe('Lead — Operatore auto-fills an empty Sede (AC-061, AC-027)', () => {
  it('fills the empty Sede from the picked Operator meta', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Operator' })

    pickInRow('Operator', 'Operator')
    fireEvent.click(pencilOf('Site'))

    expect(screen.getByTestId('select-Site')).toHaveTextContent('66')
  })

  it('an Operator without an employment Sede leaves the Sede as it is', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Operator' })

    // First pick: OPERATOR_WITH_SITE (Sede 66); second: OPERATOR_NO_SITE.
    fireEvent.click(pencilOf('Operator'))
    fireEvent.click(screen.getByTestId('select-Operator'))
    fireEvent.click(screen.getByTestId('select-Operator'))
    expect(screen.getByTestId('select-Operator')).toHaveTextContent(String(OPERATOR_NO_SITE.id))
    confirmOpenRow('Done')

    fireEvent.click(pencilOf('Site'))
    expect(screen.getByTestId('select-Site')).toHaveTextContent('66')
  })

  it('AC-026: never overwrites a Sede already chosen (spec 0103 D-13)', async () => {
    updateLeadMock.mockResolvedValue(PLACED_LEAD)
    renderDetail(PLACED_LEAD)

    // OPERATOR_NO_SITE -> OPERATOR_WITH_SITE, whose physical Sede (66) differs from SITE_A.
    pickInRow('Operator', 'Operator', 'Save')

    await waitFor(() => expect(updateLeadMock).toHaveBeenCalledWith(9, { operator_id: OPERATOR_WITH_SITE.id }))
  })
})

describe('Lead — changing the Sede clears the Operatore (AC-062)', () => {
  it('clears a previously picked Operator once the Sede changes (create)', async () => {
    createLeadMock.mockResolvedValue(PLACED_LEAD)
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Registry' })

    // The Operator fills the Sede with 66; a Site pick then moves it to SITE_A.
    pickInRow('Operator', 'Operator')
    pickInRow('Site', 'Site')
    pickInRow('Registry', 'Registry')
    pickInRow('Campaign', 'Campaign')
    pickInRow('Source', 'Source')
    clickCreateSave()

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0]).toMatchObject({ operational_site_id: SITE_A.id, operator_id: null })
  })

  it('sends the cleared Operator with the new Sede in one PATCH (detail)', async () => {
    updateLeadMock.mockResolvedValue(PLACED_LEAD)
    renderDetail(PLACED_LEAD)

    pickInRow('Site', 'Site', 'Save')

    await waitFor(() =>
      expect(updateLeadMock).toHaveBeenCalledWith(9, { operational_site_id: SITE_B.id, operator_id: null }),
    )
  })

  it("a different field's change never clears the Operator", async () => {
    updateLeadMock.mockResolvedValue(PLACED_LEAD)
    renderDetail(PLACED_LEAD)

    pickInRow('Source', 'Source', 'Save')

    await waitFor(() => expect(updateLeadMock).toHaveBeenCalledWith(9, { source_id: 3 }))
  })

  it('a cancelled Sede change does not leak into the next row (baseline re-anchored)', async () => {
    updateLeadMock.mockResolvedValue(PLACED_LEAD)
    renderDetail(PLACED_LEAD)

    fireEvent.click(pencilOf('Site'))
    fireEvent.click(screen.getByTestId('select-Site'))
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    pickInRow('Source', 'Source', 'Save')

    await waitFor(() => expect(updateLeadMock).toHaveBeenCalledWith(9, { source_id: 3 }))
  })
})
