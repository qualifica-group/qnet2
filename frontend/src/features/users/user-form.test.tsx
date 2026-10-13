import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import i18n from '@/i18n'
import type { ResourceMeta } from '@/features/authorization/types'
import { UserDetailView } from '@/features/users/user-detail'
import { UserForm } from '@/features/users/user-form'
import {
  clickCreateSave,
  confirmOpenRow,
  fillCardNames,
  fillRequiredCreateRows,
  focusedInput,
  pencilOf,
  permissionsFor,
  typeInRow,
  userCard,
  userFixture,
  userWrapper,
} from '@/features/users/user-test-fixtures'
import type { UserDetailWithPermissions } from '@/features/users/types'

/**
 * The user create form and the anagraphic card (spec 0222): the card is ONE
 * row, mandatory on create and validated before any request; contacts and
 * addresses wait in the draft (side cards) and travel nested in the single
 * create; the password row picks invite vs temporary password (spec 0177).
 * REQUIREMENT CHANGED (spec 0222): rows are closed until opened, and the
 * edit cases run on the in-place detail.
 */

const fetchUserMock = vi.fn()
const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/users/api')>()),
  fetchUser: (...args: unknown[]) => fetchUserMock(...args),
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// The quick-create "+" (spec 0028) is gated by `Can`/`useAbilities`, which needs
// an `AuthProvider` these suites do not render: deny everything so it never mounts.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))
vi.mock('@/features/modules/use-module-open-mode', () => ({ useModuleOpenMode: () => 'modal' }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/features/config/use-config', async () => {
  const { USER_ENUMS } = await import('@/features/users/user-test-fixtures')
  return {
    useConfig: () => ({ data: { enums: USER_ENUMS } }),
    useEnumOptions: (key: string) => USER_ENUMS[key] ?? [],
  }
})

vi.mock('@/features/for-select/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/for-select/api')>()),
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 }, export_link: null }),
}))

vi.mock('@/components/ui/async-paginated-select', async () => ({
  AsyncPaginatedSelect: (await import('@/features/users/user-test-stubs')).StubAsyncSelect,
}))
vi.mock('@/components/ui/async-paginated-multi-select', async () => ({
  AsyncPaginatedMultiSelect: (await import('@/features/users/user-test-stubs')).StubMultiSelect,
}))
vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: () => <div>activity-log-section</div>,
}))

function renderCreate() {
  const { client, wrapper } = userWrapper()
  render(<UserForm onSuccess={vi.fn()} onCancel={vi.fn()} />, { wrapper })
  return client
}

async function renderDetail(user: UserDetailWithPermissions) {
  fetchUserMock.mockResolvedValue(user)
  const { client, wrapper } = userWrapper()
  render(<UserDetailView userId={user.id} />, { wrapper })
  await screen.findByRole('button', { name: 'Edit Personal details' })
  return client
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.restoreAllMocks()
  fetchUserMock.mockReset()
  createUserMock.mockReset()
  updateUserMock.mockReset()
  createUserMock.mockResolvedValue(userFixture())
  updateUserMock.mockResolvedValue(userFixture())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: permissionsFor() })
})

/** The side column's card titled `title` (Contacts / Addresses). */
function sideCard(title: string): HTMLElement {
  return screen.getByText(title).closest('section') as HTMLElement
}

describe('UserForm — create with the nested personal data', () => {
  it('offers the anagraphic card as one closed row, no add/remove affordance', async () => {
    renderCreate()

    expect(await screen.findByRole('button', { name: 'Edit Personal details' })).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Add personal data' })).not.toBeInTheDocument()
  })

  it('submits one createUser call carrying the nested personal_data, no name, and the invite (no password)', async () => {
    renderCreate()

    await fillRequiredCreateRows()
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    const payload = createUserMock.mock.calls[0][0]
    expect(payload.name).toBeUndefined()
    expect(payload.email).toBe('ada@example.com')
    expect(payload).not.toHaveProperty('password')
    expect(payload.personal_data).toMatchObject({ type: 'individual', first_name: 'Ada', last_name: 'Lovelace' })
    expect(payload.personal_data.contacts).toEqual([])
    expect(payload.personal_data.addresses).toEqual([])
  })

  it('sends a typed temporary password with its confirmation (spec 0177)', async () => {
    renderCreate()

    await fillRequiredCreateRows()
    fireEvent.click(pencilOf('Password'))
    fireEvent.change(focusedInput('Password'), { target: { value: 'secret123' } })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 'secret123' } })
    confirmOpenRow('Done')
    expect(screen.getByText('Temporary password set')).toBeInTheDocument()
    clickCreateSave()

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    expect(createUserMock.mock.calls[0][0]).toMatchObject({ password: 'secret123', password_confirmation: 'secret123' })
  })

  it('refuses the save until the card is complete, naming the missing fields and reopening its row', async () => {
    renderCreate()
    await screen.findByRole('button', { name: 'Edit Personal details' })

    typeInRow('Email', 'grace@example.com')
    clickCreateSave()

    expect(
      await screen.findByText(
        'Complete the required personal data fields: First name: First name is required. · Last name: Last name is required.',
      ),
    ).toBeInTheDocument()
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(createUserMock).not.toHaveBeenCalled()
  })

  it('refuses the save when a quick contact is invalid', async () => {
    renderCreate()

    await fillRequiredCreateRows()
    fireEvent.change(within(sideCard('Contacts')).getByLabelText('Email'), { target: { value: 'not-an-email' } })
    clickCreateSave()

    expect(await screen.findByText('Fix the contacts: Email: Enter a valid email address.')).toBeInTheDocument()
    expect(createUserMock).not.toHaveBeenCalled()
  })

  it('refuses the save when an address is started but missing the city', async () => {
    renderCreate()

    await fillRequiredCreateRows()
    fireEvent.change(within(sideCard('Addresses')).getByLabelText(/^Address\*?$/), { target: { value: 'Via Roma 1' } })
    clickCreateSave()

    expect(await screen.findByText('Complete the address: City: The city is required.')).toBeInTheDocument()
    expect(createUserMock).not.toHaveBeenCalled()
  })
})

describe('UserDetailView — the anagraphic card row (spec 0222 D-3)', () => {
  it('PATCHes the card alone, with its children, when the card row is saved', async () => {
    const card = userCard({
      contacts: [
        {
          id: 5,
          type: 'email',
          label: 'Work',
          value: 'ada@work.com',
          is_primary: true,
          contactable_type: 'personal_data',
          contactable_id: 99,
          created_at: null,
        },
      ],
    })
    await renderDetail(userFixture({ personal_data: card }))

    await fillCardNames('Save', 'Augusta', 'Lovelace')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateUserMock.mock.calls[0]
    expect(id).toBe(7)
    expect(Object.keys(payload)).toEqual(['personal_data'])
    expect(payload.personal_data.first_name).toBe('Augusta')
    expect(payload.personal_data.contacts[0]).toMatchObject({ id: 5, value: 'ada@work.com' })
  })

  it('never sends the card (nor the employment) when another row is saved', async () => {
    await renderDetail(userFixture())

    typeInRow('Email', 'ada@new.example.com', 'Save')

    await waitFor(() => expect(updateUserMock).toHaveBeenCalledWith(7, { email: 'ada@new.example.com' }))
  })

  it('shows the profile photo control to an actor allowed to upload', async () => {
    await renderDetail(userFixture({ permissions: permissionsFor({}, { upload_avatar: true, delete_avatar: true }) }))

    expect(screen.getAllByText('Avatar').length).toBeGreaterThan(0)
  })
})
