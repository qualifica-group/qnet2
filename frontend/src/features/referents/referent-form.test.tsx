import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import {
  clickCreateSave,
  confirmOpenRow,
  fillCardNames,
  fillRequiredPhone,
  pencilOf,
  permissionsFor,
  referentFixture,
  referentWrapper,
} from '@/features/referents/referent-test-fixtures'

/**
 * The referente create form (spec 0216 D-3), a replica of the in-place detail:
 * every row CLOSED until clicked, "Done"/"Revert" per row, the header's Save
 * validating and creating the whole draft. REQUIREMENT CHANGED (spec 0216):
 * the controls are no longer laid out open, and there is no edit mode — the
 * edit cases live in `referent-detail-inline-edit.test.tsx`.
 */

const createReferentMock = vi.fn()

vi.mock('@/features/referents/api', () => ({
  createReferent: (...args: unknown[]) => createReferentMock(...args),
  updateReferent: vi.fn(),
  referentDetailQueryKey: (id: number | null) => ['referents', 'detail', id] as const,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

const enums: Record<string, EnumOption[]> = {
  personal_data_type: [
    { value: 'individual', label: 'Individual', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'company', label: 'Company', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
  contact_type: [],
  referent_contact_scope: [
    { value: 'internal', label: 'Internal', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'external', label: 'External', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

// A controllable stub of the async single-select pickers (referent type,
// linked user), keyed by `resource`.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ onChange, resource }: { onChange: (value: number | null) => void; resource: string }) => (
    <button type="button" onClick={() => onChange(3)}>
      {`select-${resource}-3`}
    </button>
  ),
}))

function renderForm(onSuccess = vi.fn()) {
  const { client, wrapper } = referentWrapper()
  render(<ReferentForm onSuccess={onSuccess} onCancel={vi.fn()} />, { wrapper })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createReferentMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

describe('ReferentForm — create as closed rows (spec 0216)', () => {
  it('starts every row closed, contact scope pre-set to internal', async () => {
    renderForm()

    expect(await screen.findByRole('button', { name: 'Edit Personal details' })).toBeInTheDocument()
    expect(pencilOf('Referent type')).toBeInTheDocument()
    expect(pencilOf('Linked user')).toBeInTheDocument()
    expect(pencilOf('Notes')).toBeInTheDocument()
    expect(pencilOf('Contact scope')).toBeInTheDocument()
    expect(screen.getAllByText('Internal').length).toBeGreaterThan(0)
    expect(screen.queryByLabelText(/^First name/)).not.toBeInTheDocument()
    expect(screen.queryByText('Activity sectors')).not.toBeInTheDocument()
  })

  it('names the referente in the header once the card is kept with Done', async () => {
    renderForm()

    await fillCardNames('Grace', 'Hopper')

    expect(screen.getByRole('heading', { name: 'Grace Hopper' })).toBeInTheDocument()
  })

  it('submits one createReferent call carrying the nested personal_data and referent fields', async () => {
    createReferentMock.mockResolvedValue(referentFixture())
    const onSuccess = vi.fn()
    renderForm(onSuccess)

    await fillCardNames()
    fillRequiredPhone()
    fireEvent.click(pencilOf('Referent type'))
    fireEvent.click(screen.getByText('select-referent-types-3'))
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Linked user'))
    fireEvent.click(screen.getByText('select-users-3'))
    confirmOpenRow('Done')
    fireEvent.click(pencilOf('Notes'))
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'VIP sponsor' } })
    confirmOpenRow('Done')
    clickCreateSave()

    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    const payload = createReferentMock.mock.calls[0][0]
    expect(payload.referent_type_id).toBe(3)
    expect(payload.user_id).toBe(3)
    expect(payload.contact_scope).toBe('internal')
    expect(payload.notes).toBe('VIP sponsor')
    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(referentFixture()))
  })

  it('refuses the save on an incomplete card and reopens its row', async () => {
    renderForm()
    await screen.findByRole('button', { name: 'Edit Personal details' })

    clickCreateSave()

    expect(await screen.findByText(/^Complete the required personal data fields: /)).toBeInTheDocument()
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(createReferentMock).not.toHaveBeenCalled()
  })

  it('keeps the card row open on Done while it is incomplete', async () => {
    renderForm()

    fireEvent.click(await screen.findByRole('button', { name: 'Edit Personal details' }))
    confirmOpenRow('Done')

    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
  })

  it('blocks the save when no phone number was entered (user directive 2026-07-31)', async () => {
    renderForm()

    await fillCardNames()
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'ada@example.com' } })
    clickCreateSave()

    expect(await screen.findByText('Enter at least one phone number.')).toBeInTheDocument()
    expect(createReferentMock).not.toHaveBeenCalled()
  })

  it('marks the phone quick field as required', async () => {
    renderForm()

    expect(await screen.findByLabelText(/^Phone/)).toHaveAttribute('aria-required', 'true')
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-required', 'false')
  })

  it('invalidates the referents module statistics after a successful save (spec 0026)', async () => {
    createReferentMock.mockResolvedValue(referentFixture())
    const client = renderForm()
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    await fillCardNames()
    fillRequiredPhone()
    clickCreateSave()

    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['stats', 'referents'] })
  })
})
