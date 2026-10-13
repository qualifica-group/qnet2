import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadForm } from '@/features/leads/lead-form'
import type { ResourceMeta } from '@/features/authorization/types'
import {
  EDITABLE,
  HIDDEN,
  READ_ONLY,
  clickCreateSave,
  confirmOpenRow,
  leadFixture,
  leadWrapper,
  pencilOf,
  permissionsFor,
  pickInRow,
} from '@/features/leads/lead-test-fixtures'

/**
 * The lead create form (spec 0217 D-4), a replica of the in-place detail:
 * every row CLOSED until clicked, "Done"/"Revert" per row, the header's Save
 * validating and creating the whole draft. Covers AC-061 (the fields), AC-062
 * (field permissions), AC-014 (extra fields) and spec 0044 (conversion).
 * REQUIREMENT CHANGED (spec 0217): the controls are no longer laid out open,
 * and there is no edit mode — the edit cases live in
 * `lead-detail-inline-edit.test.tsx`.
 */

const createLeadMock = vi.fn()

vi.mock('@/features/leads/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/leads/api')>()),
  createLead: (...args: unknown[]) => createLeadMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

/** Grants `opportunities.create` by default so the conversion row (AC-040) renders unless overridden. */
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

// The closed rows link the picked anagrafica/campaign (`RecordLink`), which resolves the user's open mode.
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/leads/lead-test-stubs')).StubSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/leads/lead-test-stubs')).StubMultiSelect,
}))

function renderForm() {
  const { wrapper } = leadWrapper()
  render(<LeadForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
}

/** The three required rows: Registry (id 3), Campaign (the plain one, no prefill), Source (id 3). */
function fillRequired() {
  pickInRow('Registry', 'Registry')
  pickInRow('Campaign', 'Campaign')
  pickInRow('Source', 'Source')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createLeadMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
  canMock.mockReset()
  canMock.mockReturnValue(true)
})

describe('LeadForm — create as closed rows (AC-061, spec 0217)', () => {
  it('starts every row closed, each opening on its own control', async () => {
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Registry' })).toBeInTheDocument()
    for (const field of ['Campaign', 'Site', 'Operator', 'Source', 'Products of interest', 'Imported data', 'Notes']) {
      expect(pencilOf(field)).toBeInTheDocument()
    }
    expect(screen.queryByTestId('select-Registry')).not.toBeInTheDocument()

    fireEvent.click(pencilOf('Notes'))
    expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument()
  })

  it('refuses the save while a required row is empty, marking it on its closed row', async () => {
    renderForm()
    await screen.findByRole('button', { name: 'Edit Registry' })

    clickCreateSave()

    expect(await screen.findByText('Registry is required.')).toBeInTheDocument()
    expect(screen.getByText('Campaign is required.')).toBeInTheDocument()
    expect(createLeadMock).not.toHaveBeenCalled()
  })
})

describe('LeadForm — field permissions (AC-062)', () => {
  it('does not render a row marked hidden', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor({ operator_id: HIDDEN }) })
    renderForm()

    await screen.findByRole('button', { name: 'Edit Registry' })
    expect(screen.queryByText('Operator', { selector: 'dt' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Edit Operator' })).not.toBeInTheDocument()
  })

  it('shows a non-editable row without a pencil', async () => {
    fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor({ notes: READ_ONLY }) })
    renderForm()

    await screen.findByRole('button', { name: 'Edit Registry' })
    expect(screen.queryByRole('button', { name: 'Edit Notes' })).not.toBeInTheDocument()
  })

  it('keeps the Site row closed when the Operator its change clears is not editable (spec 0195 D-6c)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: permissionsFor({ operator_id: READ_ONLY, operational_site_id: EDITABLE }),
    })
    renderForm()

    await screen.findByRole('button', { name: 'Edit Registry' })
    expect(screen.queryByRole('button', { name: 'Edit Site' })).not.toBeInTheDocument()
  })
})

describe('LeadForm — imported data editor (AC-014)', () => {
  it('opens on the empty state, adds, edits and removes rows', async () => {
    renderForm()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Imported data' }))
    expect(screen.getByText('No extra fields.')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    expect(screen.getAllByRole('textbox', { name: 'Key' })).toHaveLength(2)

    fireEvent.click(screen.getAllByRole('button', { name: 'Remove field' })[0])
    expect(screen.getAllByRole('textbox', { name: 'Key' })).toHaveLength(1)
    fireEvent.change(screen.getByRole('textbox', { name: 'Key' }), { target: { value: 'Original column' } })
    fireEvent.change(screen.getByRole('textbox', { name: 'Value' }), { target: { value: 'foo' } })
    confirmOpenRow('Done')

    expect(screen.getByText('Original column')).toBeInTheDocument()
    expect(screen.getByText('foo')).toBeInTheDocument()
  })

  it('sends the kept pairs as extra_fields', async () => {
    createLeadMock.mockResolvedValue(leadFixture())
    renderForm()
    await screen.findByRole('button', { name: 'Edit Registry' })

    fillRequired()
    fireEvent.click(pencilOf('Imported data'))
    fireEvent.click(screen.getByRole('button', { name: 'Add field' }))
    fireEvent.change(screen.getByRole('textbox', { name: 'Key' }), { target: { value: 'Original column' } })
    fireEvent.change(screen.getByRole('textbox', { name: 'Value' }), { target: { value: 'foo' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0].extra_fields).toEqual({ 'Original column': 'foo' })
  })
})

describe('LeadForm — convert to opportunity (spec 0044, AC-040..AC-043)', () => {
  it('AC-040: shows the row, ON by default, when the actor has opportunities.create', async () => {
    renderForm()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Automatically convert to Opportunity' }))
    expect(screen.getByRole('switch', { name: 'Automatically convert to Opportunity' })).toBeChecked()
  })

  it('AC-040: hides the row without opportunities.create', async () => {
    canMock.mockReturnValue(false)
    renderForm()

    await screen.findByRole('button', { name: 'Edit Registry' })
    expect(screen.queryByText('Automatically convert to Opportunity')).not.toBeInTheDocument()
  })

  it('directive 2026-07-21 (supersedes AC-041): submits with the conversion on and Operator/Site empty', async () => {
    createLeadMock.mockResolvedValue(leadFixture())
    renderForm()
    await screen.findByRole('button', { name: 'Edit Registry' })

    fillRequired()
    clickCreateSave()

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    const payload = createLeadMock.mock.calls[0][0]
    expect(payload).toMatchObject({ registry_id: 3, campaign_id: 20, source_id: 3, convert_to_opportunity: true })
    expect(payload.operator_id).toBeNull()
    expect(payload.operational_site_id).toBeNull()
  })

  it('AC-042: the conversion can be turned off', async () => {
    createLeadMock.mockResolvedValue(leadFixture())
    renderForm()
    await screen.findByRole('button', { name: 'Edit Registry' })

    fillRequired()
    fireEvent.click(pencilOf('Automatically convert to Opportunity'))
    fireEvent.click(screen.getByRole('switch', { name: 'Automatically convert to Opportunity' }))
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createLeadMock).toHaveBeenCalledTimes(1))
    expect(createLeadMock.mock.calls[0][0].convert_to_opportunity).toBe(false)
  })
})
