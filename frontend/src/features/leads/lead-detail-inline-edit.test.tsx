import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { LeadDetailView } from '@/features/leads/lead-detail'
import {
  CAMPAIGN_PREFILL,
  EDITABLE,
  READ_ONLY,
  confirmOpenRow,
  leadFixture,
  leadWrapper,
  pencilOf,
  permissionsFor,
  stubState,
} from '@/features/leads/lead-test-fixtures'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/**
 * Spec 0217: the lead detail edits in place, one row at a time. Each save is
 * a PATCH of that field alone (plus what its cascade wrote).
 */

const updateLeadMock = vi.fn()
vi.mock('@/features/leads/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/leads/api')>()),
  updateLead: (...args: unknown[]) => updateLeadMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

function renderDetail(lead: LeadDetailWithPermissions) {
  const { client, wrapper } = leadWrapper()
  render(<LeadDetailView lead={lead} />, { wrapper })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  updateLeadMock.mockReset()
  stubState.campaigns = [CAMPAIGN_PREFILL]
})

describe('LeadDetailView — in-place editing (spec 0217)', () => {
  it('PATCHes the edited note alone and seeds the cache with the permissions', async () => {
    const saved = leadFixture({ notes: 'Call back Monday.' })
    updateLeadMock.mockResolvedValue(saved)
    const client = renderDetail(leadFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Call back Monday.' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateLeadMock).toHaveBeenCalledWith(9, { notes: 'Call back Monday.' }))
    expect(client.getQueryData<LeadDetailWithPermissions>(['leads', 'detail', 9])?.permissions).toEqual(
      saved.permissions,
    )
    await waitFor(() => expect(screen.queryByRole('textbox', { name: 'Notes' })).not.toBeInTheDocument())
  })

  // Bug 2026-10-12 (browser): a pencil clicked mid-save reset the form under the save, then closed again.
  it('disables the other pencils while a save is in flight', async () => {
    let resolveSave: (lead: LeadDetailWithPermissions) => void = () => undefined
    updateLeadMock.mockReturnValue(new Promise((resolve) => (resolveSave = resolve)))
    renderDetail(leadFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Call back.' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(pencilOf('Source')).toBeDisabled())
    resolveSave(leadFixture({ notes: 'Call back.' }))
    await waitFor(() => expect(pencilOf('Source')).not.toBeDisabled())
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(leadFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByRole('textbox', { name: 'Notes' })).not.toBeInTheDocument()
    expect(screen.getByText('Original note.')).toBeInTheDocument()
    expect(updateLeadMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(leadFixture({ permissions: permissionsFor({ source_id: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Source' })).not.toBeInTheDocument()
    expect(pencilOf('Registry')).toBeInTheDocument()
  })

  it('keeps the Campaign row closed when a field its cascade writes is not editable (spec 0195 D-6c)', () => {
    renderDetail(
      leadFixture({ permissions: permissionsFor({ operational_site_id: READ_ONLY, campaign_id: EDITABLE }) }),
    )

    expect(screen.queryByRole('button', { name: 'Edit Campaign' })).not.toBeInTheDocument()
  })

  it('maps a 422 onto the open field with the accessible error triad (AC-064)', async () => {
    updateLeadMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Validation failed', errors: { notes: ['Notes are too long.'] } },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
    renderDetail(leadFixture())

    fireEvent.click(pencilOf('Notes'))
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveAttribute('aria-invalid', 'false')
    fireEvent.change(screen.getByRole('textbox', { name: 'Notes' }), { target: { value: 'x' } })
    confirmOpenRow('Save')

    const message = await screen.findByText('Notes are too long.')
    const notes = screen.getByRole('textbox', { name: 'Notes' })
    expect(notes).toHaveAttribute('aria-invalid', 'true')
    expect(notes.getAttribute('aria-describedby')).toContain(message.id)
  })

  it('edits the imported data as one row and PATCHes the whole record', async () => {
    updateLeadMock.mockResolvedValue(leadFixture())
    renderDetail(leadFixture({ extra_fields: { 'Original column': 'foo' } }))

    fireEvent.click(pencilOf('Imported data'))
    expect(screen.getByRole('textbox', { name: 'Key' })).toHaveValue('Original column')
    fireEvent.change(screen.getByRole('textbox', { name: 'Value' }), { target: { value: 'bar' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateLeadMock).toHaveBeenCalledWith(9, { extra_fields: { 'Original column': 'bar' } }),
    )
  })

  it('shows no conversion row: the conversion is create-only (spec 0044)', () => {
    renderDetail(leadFixture())

    expect(screen.queryByText('Automatically convert to Opportunity')).not.toBeInTheDocument()
  })
})
