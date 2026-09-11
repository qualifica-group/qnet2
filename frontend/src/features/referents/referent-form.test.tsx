import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/* ----------------------------- module mocks ------------------------------- */

const createReferentMock = vi.fn()
const updateReferentMock = vi.fn()

vi.mock('@/features/referents/api', () => ({
  createReferent: (...args: unknown[]) => createReferentMock(...args),
  updateReferent: (...args: unknown[]) => updateReferentMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/**
 * This suite is not about authorization metadata (covered by
 * `referent-form-metadata.test.tsx`): every field resolves as
 * visible+editable (the `MetaField` fallback, since `fields` is empty).
 */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/referents/use-referent-form-meta', () => ({
  useReferentFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// `useCustomFieldsForm` (spec 0021) reads the resource meta directly, bypassing
// `useReferentFormMeta` above: stub it with no custom fields defined, so this
// suite (not about custom fields) renders exactly as it did before.
const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// Enum options consumed by the personal-data card form and the contact-scope
// select (controlled, no network).
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

// Replace the async single-select pickers (referent type, linked user) with a
// lightweight controllable stub so this suite focuses on the form's own
// logic. Keyed by `resource` since the form now mounts two of these
// (referent-types, users, spec 0090 T-08).
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    value,
    onChange,
    resource,
  }: {
    value: number | null
    onChange: (value: number | null) => void
    resource: string
  }) => (
    <div>
      <span data-testid={`${resource}-value`}>{value ?? ''}</span>
      <button type="button" onClick={() => onChange(3)}>
        {`select-${resource}-3`}
      </button>
      <button type="button" onClick={() => onChange(null)}>
        {`clear-${resource}`}
      </button>
    </div>
  ),
}))

/* -------------------------------- helpers --------------------------------- */

/** Creating a referent requires a phone number (user directive 2026-07-31). */
function fillRequiredPhone() {
  fireEvent.change(screen.getByLabelText(/^Phone/), { target: { value: '+39 333 1234567' } })
}

function wrapper(client: QueryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })) {
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
    residence_city_id: null,
    gender: null,
    personable_type: 'referent',
    personable_id: 7,
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
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

function referent(
  overrides: Partial<ReferentDetailWithPermissions> = {},
): ReferentDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    referent_type_id: 3,
    referent_type: { id: 3, name: 'Sponsor' },
    user_id: null,
    user: null,
    contact_scope: 'internal',
    notes: 'Some notes',
    personal_data: card(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createReferentMock.mockReset()
  updateReferentMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
})

describe('ReferentForm — create/edit (AC-020, AC-021, AC-022)', () => {
  it('renders the anagraphic, details and activity-sectors placeholder in create mode', () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The anagraphic card (PersonalDataCardForm reused) opens the single screen.
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(screen.getByLabelText(/^Last name/)).toBeInTheDocument()

    expect(screen.getByTestId('referent-types-value')).toBeInTheDocument()
    expect(screen.getByTestId('users-value')).toBeInTheDocument()
    // contact_scope pre-selects 'internal'.
    expect(screen.getByRole('combobox', { name: 'Contact scope' })).toHaveTextContent('Internal')
    // Activity sectors: disabled placeholder, not wired to any field.
    expect(screen.getByText('Activity sectors')).toBeInTheDocument()
    expect(screen.getByText('Coming soon')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Notes/)).toBeInTheDocument()
  })

  it('submits one createReferent call carrying the nested personal_data and referent fields', async () => {
    createReferentMock.mockResolvedValue(referent())
    const onSuccess = vi.fn()

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })

    fillRequiredPhone()

    fireEvent.click(screen.getByText('select-referent-types-3'))
    fireEvent.click(screen.getByText('select-users-3'))
    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'VIP sponsor' } })

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    const payload = createReferentMock.mock.calls[0][0]
    expect(payload.referent_type_id).toBe(3)
    expect(payload.user_id).toBe(3)
    expect(payload.contact_scope).toBe('internal')
    expect(payload.notes).toBe('VIP sponsor')
    expect(payload.personal_data.first_name).toBe('Ada')
    expect(payload.personal_data.last_name).toBe('Lovelace')
    await waitFor(() => expect(onSuccess).toHaveBeenCalledWith(referent()))
  })

  it('blocks the save until the required personal-data fields are filled', async () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(
        screen.getByText(/^Complete the required personal data fields: /),
      ).toBeInTheDocument(),
    )
    expect(createReferentMock).not.toHaveBeenCalled()
  })

  it('blocks the save when no phone number was entered (user directive 2026-07-31)', async () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: 'ada@example.com' } })

    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('Enter at least one phone number.')).toBeInTheDocument(),
    )
    expect(createReferentMock).not.toHaveBeenCalled()
  })

  it('marks the phone quick field as required in create mode', () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )


    expect(screen.getByLabelText(/^Phone/)).toHaveAttribute('aria-required', 'true')
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-required', 'false')
  })

  it('seeds referent fields and the anagraphic card from the loaded detail in edit mode', async () => {
    render(
      <ReferentForm
        mode={{ type: 'edit', referent: referent({ user_id: 9, user: { id: 9, name: 'Mario Rossi' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    // Seeded card fields — no separate fetch needed,
    // `personal_data` arrives embedded in the `show` response (spec 0016).
    expect(screen.getByLabelText(/^First name/)).toHaveValue('Ada')

    expect(screen.getByTestId('referent-types-value')).toHaveTextContent('3')
    expect(screen.getByTestId('users-value')).toHaveTextContent('9')
    expect(screen.getByLabelText(/^Notes/)).toHaveValue('Some notes')

    expect(screen.getByText('ada@work.com')).toBeInTheDocument()
  })

  it('submits only the changed fields on a partial update', async () => {
    updateReferentMock.mockResolvedValue(referent({ notes: 'Updated note' }))

    render(
      <ReferentForm
        mode={{ type: 'edit', referent: referent() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Notes/), { target: { value: 'Updated note' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateReferentMock.mock.calls[0]
    expect(id).toBe(7)
    expect(payload).toEqual({ notes: 'Updated note' })
  })

  it('unlinks the user without touching any other field (AC-003, spec 0090)', async () => {
    updateReferentMock.mockResolvedValue(referent({ user_id: null, user: null }))

    render(
      <ReferentForm
        mode={{ type: 'edit', referent: referent({ user_id: 9, user: { id: 9, name: 'Mario Rossi' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByText('clear-users'))
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledTimes(1))
    const [, payload] = updateReferentMock.mock.calls[0]
    expect(payload).toEqual({ user_id: null })
  })

  it('invalidates the referents module statistics after a successful save (spec 0026)', async () => {
    createReferentMock.mockResolvedValue(referent())
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const invalidateSpy = vi.spyOn(client, 'invalidateQueries')

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper(client) },
    )

    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })
    fillRequiredPhone()
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    expect(invalidateSpy).toHaveBeenCalledWith({ queryKey: ['stats', 'referents'] })
  })
})
