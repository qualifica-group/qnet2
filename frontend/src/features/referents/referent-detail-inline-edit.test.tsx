import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import {
  EDITABLE,
  READ_ONLY,
  confirmOpenRow,
  pencilOf,
  permissionsFor,
  referentFixture,
  referentWrapper,
} from '@/features/referents/referent-test-fixtures'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

// Spec 0312: the notes tab mounts the agnostic NotesSection, covered by `notes-section.test.tsx`.
// REQUIREMENT CHANGED (spec 0312): the Notes tab panel is labelled "Notes" too, so the Notes
// FIELD is addressed by its textbox role.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number }) => (
    <div>{`notes-section:${props.entityType}:${props.entityId}`}</div>
  ),
}))

/**
 * Spec 0216: the referente detail edits in place, one row at a time, custom
 * fields ("campi flessibili") and the anagraphic card included. Each save is
 * a PATCH of that field alone.
 */

const updateReferentMock = vi.fn()
vi.mock('@/features/referents/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/referents/api')>()),
  updateReferent: (...args: unknown[]) => updateReferentMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/personal-data/addresses-manager', () => ({ AddressesManager: () => null }))
vi.mock('@/features/personal-data/contacts-manager', () => ({ ContactsManager: () => null }))

// A controllable stub of the async single-select pickers (referent type, linked user).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ onChange, resource }: { onChange: (value: number | null) => void; resource: string }) => (
    <button type="button" onClick={() => onChange(null)}>
      {`clear-${resource}`}
    </button>
  ),
}))

const SPONSOR_LEVEL_FIELD: CustomFieldDescriptor = {
  key: 'custom.sponsor_level',
  type: 'text',
  label: 'Sponsor level',
  group: null,
  mandatory: false,
  source: 'custom',
}

function renderDetail(referent: ReferentDetailWithPermissions) {
  const { client, wrapper } = referentWrapper()
  render(<ReferentDetailView referent={referent} />, { wrapper })
  return client
}

function rejectWith422(errors: Record<string, string[]>) {
  updateReferentMock.mockRejectedValue(
    new AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: { success: false, message: 'Validation failed', errors },
    } as never),
  )
  vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  updateReferentMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({
    fields: [SPONSOR_LEVEL_FIELD],
    permissions: permissionsFor({ 'custom.sponsor_level': EDITABLE }),
  })
})

describe('ReferentDetailView — in-place editing (spec 0216)', () => {
  it('PATCHes the edited field alone and seeds the cache with the permissions', async () => {
    const saved = referentFixture({ notes: 'Updated note' })
    updateReferentMock.mockResolvedValue(saved)
    const client = renderDetail(referentFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: /^Notes/ }), { target: { value: 'Updated note' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledWith(7, { notes: 'Updated note' }))
    expect(client.getQueryData<ReferentDetailWithPermissions>(['referents', 'detail', 7])?.permissions).toEqual(
      saved.permissions,
    )
    await waitFor(() => expect(screen.queryByRole('textbox', { name: /^Notes/ })).not.toBeInTheDocument())
  })

  it('unlinks the user without touching any other field (spec 0090 AC-003)', async () => {
    updateReferentMock.mockResolvedValue(referentFixture())
    renderDetail(referentFixture({ user_id: 9, user: { id: 9, name: 'Mario Rossi' } }))

    fireEvent.click(pencilOf('Linked user'))
    fireEvent.click(screen.getByText('clear-users'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledWith(7, { user_id: null }))
  })

  it('unlinks the supplier registry without touching any other field (spec 0267 D-7)', async () => {
    updateReferentMock.mockResolvedValue(referentFixture())
    renderDetail(referentFixture({ supplier_registry_id: 12, supplier: { id: 12, name: 'Acme Agenti' } }))

    fireEvent.click(pencilOf('Supplier registry'))
    fireEvent.click(screen.getByText('clear-registries'))
    confirmOpenRow('Save')

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledWith(7, { supplier_registry_id: null }))
  })

  it('closes on Cancel without calling the server', () => {
    renderDetail(referentFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: /^Notes/ }), { target: { value: 'Changed' } })
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    expect(screen.queryByRole('textbox', { name: /^Notes/ })).not.toBeInTheDocument()
    expect(screen.getByText('VIP sponsor')).toBeInTheDocument()
    expect(updateReferentMock).not.toHaveBeenCalled()
  })

  it('offers no pencil on a field the actor cannot edit', () => {
    renderDetail(referentFixture({ permissions: permissionsFor({ notes: READ_ONLY }) }))

    expect(screen.queryByRole('button', { name: 'Edit Notes' })).not.toBeInTheDocument()
    expect(pencilOf('Referent type')).toBeInTheDocument()
  })

  it('shows a 422 on the edited field inside its editor', async () => {
    rejectWith422({ notes: ['Notes too long.'] })
    renderDetail(referentFixture())

    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByRole('textbox', { name: /^Notes/ }), { target: { value: 'x' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('Notes too long.')).toBeInTheDocument()
  })
})

describe('ReferentDetailView — custom fields in place (spec 0216 D-2)', () => {
  it('shows the custom field value and PATCHes only that field', async () => {
    updateReferentMock.mockResolvedValue(referentFixture({ custom_fields: { sponsor_level: 'Gold' } }))
    renderDetail(referentFixture({ custom_fields: { sponsor_level: 'Platinum' } }))

    expect(await screen.findByText('Platinum')).toBeInTheDocument()
    fireEvent.click(pencilOf('Sponsor level'))
    fireEvent.change(screen.getByRole('textbox', { name: 'Sponsor level' }), { target: { value: 'Gold' } })
    confirmOpenRow('Save')

    await waitFor(() =>
      expect(updateReferentMock).toHaveBeenCalledWith(7, { custom_fields: { sponsor_level: 'Gold' } }),
    )
  })
})

describe('ReferentDetailView — anagraphic card in place (spec 0216 D-2)', () => {
  it('sends the edited card as personal_data', async () => {
    updateReferentMock.mockResolvedValue(referentFixture())
    renderDetail(referentFixture())

    fireEvent.click(pencilOf('Personal details'))
    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Augusta' } })
    confirmOpenRow('Save')

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateReferentMock.mock.calls[0]
    expect(id).toBe(7)
    expect(Object.keys(payload)).toEqual(['personal_data'])
    expect(payload.personal_data.first_name).toBe('Augusta')
  })

  it('refuses an incomplete card without calling the server', async () => {
    renderDetail(referentFixture())

    fireEvent.click(pencilOf('Personal details'))
    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: '' } })
    confirmOpenRow('Save')

    expect(await screen.findByText(/^Complete the required personal data fields: /)).toBeInTheDocument()
    expect(updateReferentMock).not.toHaveBeenCalled()
  })

  it('surfaces a personal_data.* 422 inside the open editor', async () => {
    rejectWith422({ 'personal_data.tax_code': ['The tax code is invalid.'] })
    renderDetail(referentFixture())

    fireEvent.click(pencilOf('Personal details'))
    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Augusta' } })
    confirmOpenRow('Save')

    expect(await screen.findByText('The tax code is invalid.')).toBeInTheDocument()
  })
})
