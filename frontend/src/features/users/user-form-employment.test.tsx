import { beforeAll, afterAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0015 acceptance criteria AC-014..019 (frontend): the single-screen user
 * form, the employment fields and their i18n. The pre-existing account behaviour
 * (payload shaping, personal-data buffering, metadata gating) is covered by
 * `user-form.test.tsx`/`user-form-metadata.test.tsx`; `DurationInput` has its
 * own `duration-input.test.tsx`; the payload builder's `employment` mapping is
 * unit-tested directly in `user-form-payload.test.ts`.
 */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: { upload_avatar: true, delete_avatar: true },
}

vi.mock('@/features/users/use-user-form-meta', () => ({
  useUserFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

// This suite isn't about the quick-create "+" (spec 0028): the Account tab's
// `roles` field (`AsyncPaginatedMultiSelect`, left un-mocked below) renders a
// `QuickCreateButton` gated by `Can`/`useAbilities`, which needs an
// `AuthProvider` this suite doesn't render.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

// `useCustomFieldsForm` (spec 0021) also reads the resource meta directly,
// bypassing `useUserFormMeta` above: stub it with no custom fields defined, so
// this suite (not about custom fields) renders exactly as it did before.
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
  locale: [
    { value: 'en', label: 'English', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'it', label: 'Italiano', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

const personalDataData = vi.fn<() => PersonalDataCard | null | undefined>()
vi.mock('@/features/personal-data/use-personal-data', () => ({
  usePersonalDataByOwner: () => ({
    data: personalDataData(),
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),

}))

/**
 * Lightweight, controllable stand-in for the network-backed single select
 * (AC-016): exposes `resource` and the hydrated `selectedItem` label so tests
 * can assert wiring without a real `/for-select` fetch (covered by the
 * component's own tests).
 */
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({
    resource,
    value,
    onChange,
    selectedItem,
    labels,
  }: {
    resource: string
    value: number | null
    onChange: (value: number | null) => void
    selectedItem?: { id: number; label: string } | null
    labels: { triggerLabel: string }
  }) => (
    <div>
      <span data-testid={`resource-${labels.triggerLabel}`}>{resource}</span>
      <span data-testid={`value-${labels.triggerLabel}`}>{value ?? ''}</span>
      <span data-testid={`selected-label-${labels.triggerLabel}`}>{selectedItem?.label ?? ''}</span>
      <button type="button" onClick={() => onChange(1)}>{`pick-${labels.triggerLabel}`}</button>
      <button type="button" onClick={() => onChange(null)}>{`clear-${labels.triggerLabel}`}</button>
    </div>
  ),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

function user(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    created_at: null,
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

function userWithEmployment(): UserDetailWithPermissions {
  return user({
    employment: {
      id: 1,
      is_manager: false,
      job_description: 'Backend engineer',
      relationship_type: 'employee',
      qualification_type: 'coordinator',
      hired_at: '2026-01-15',
      terminated_at: null,
      standard_daily_minutes: 480,
      break_daily_minutes: 30,
      reports_to_id: 2,
      company_id: 5,
      primary_operational_site_id: 8,
      remote_operational_site_ids: [9, 10],
      reports_to: { id: 2, label: 'Grace Hopper' },
      company: { id: 5, label: 'Acme Srl' },
      primary_operational_site: { id: 8, label: 'Via Roma 1' },
      remote_operational_sites: [
        { id: 9, label: 'Via Milano 2' },
        { id: 10, label: 'Via Torino 3' },
      ],
    },
  })
}

/** A valid, seeded personal-data card, needed so an edit-mode submit is not blocked by AC-014's mandatory-fields gate. */
const validCard: PersonalDataCard = {
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
  personable_type: 'user',
  personable_id: 7,
  contacts: [],
  addresses: [],
  created_at: null,
}

function fillIdentity() {
  fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
  fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })
}

/**
 * The sign-in email. On the single-screen form the contacts block's quick
 * "Email" is mounted too, so the label alone is ambiguous: scope the query to
 * the Authentication block.
 */
function signInEmail(): HTMLElement {
  const section = screen.getByText('Authentication').closest('section') as HTMLElement
  return within(section).getByLabelText(/^Email/)
}

function fillCredentials() {
  fireEvent.change(signInEmail(), { target: { value: 'ada@example.com' } })
  fireEvent.change(screen.getByLabelText(/^Password/), { target: { value: 'secret123' } })
  fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 'secret123' } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  createUserMock.mockResolvedValue(user())
  updateUserMock.mockResolvedValue(user())
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
})

describe('UserForm — single-screen layout (user directive 2026-09-07)', () => {
  it('mounts the identity, credentials, employment and contact blocks together, with no macro tabs', () => {
    render(<UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    // One block per former macro tab, all reachable without a single click.
    expect(screen.getByLabelText(/^First name/)).toBeInTheDocument()
    expect(signInEmail()).toBeInTheDocument()
    expect(screen.getByText('Reports to')).toBeInTheDocument()
    expect(screen.getByLabelText(/^Phone/)).toBeInTheDocument()

    // The only tabs left on the screen are the card's individual/company
    // toggle: the macro strip is gone, dots included.
    expect(screen.queryByRole('tab', { name: /^Account/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Employment' })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Contact info' })).not.toBeInTheDocument()
  })
})

describe('UserForm — is_manager / reports_to (spec 0015 AC-015)', () => {
  it('hides reports_to and force-nulls it in the payload once is_manager is enabled', async () => {
    render(<UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fillIdentity()
    fillCredentials()

    expect(screen.getByText('Reports to')).toBeInTheDocument()

    // Two switches share the single screen now (Active, in the access block):
    // name the one under test.
    fireEvent.click(screen.getByRole('switch', { name: 'Manager' }))
    expect(screen.queryByText('Reports to')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    const payload = createUserMock.mock.calls[0][0]
    expect(payload.employment.is_manager).toBe(true)
    expect(payload.employment.reports_to_id).toBeNull()
  })

  it('keeps reports_to visible and submits its value when is_manager is false', async () => {
    render(<UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fillIdentity()
    fillCredentials()

    fireEvent.click(screen.getByText('pick-Reports to'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    const payload = createUserMock.mock.calls[0][0]
    expect(payload.employment.is_manager).toBe(false)
    expect(payload.employment.reports_to_id).toBe(1)
  })
})

describe('UserForm — employment relation selects (spec 0015 AC-016)', () => {
  it('binds each select to its contract resource, hydrated from the loaded detail in edit mode', () => {
    render(
      <UserForm mode={{ type: 'edit', user: userWithEmployment() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByTestId('resource-Reports to')).toHaveTextContent('users')
    expect(screen.getByTestId('selected-label-Reports to')).toHaveTextContent('Grace Hopper')

    expect(screen.getByTestId('resource-Company')).toHaveTextContent('companies')
    expect(screen.getByTestId('selected-label-Company')).toHaveTextContent('Acme Srl')
  })
})

describe('UserForm — employment payload + 422 mapping (spec 0015 AC-018)', () => {
  it('includes the employment object with contract snake_case keys on create', async () => {
    render(<UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    fillIdentity()
    fillCredentials()
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    const payload = createUserMock.mock.calls[0][0]
    expect(payload.employment).toEqual({
      is_manager: false,
      job_description: null,
      reports_to_id: null,
      relationship_type: null,
      company_id: null,
      primary_operational_site_id: null,
      remote_operational_site_ids: [],
      product_lines: [],
      qualification_type: null,
      hired_at: null,
      terminated_at: null,
      standard_daily_minutes: null,
      break_daily_minutes: null,
    })
  })

  it('maps a nested employment.* 422 error onto the right field, inline', async () => {
    personalDataData.mockReturnValue(validCard)
    updateUserMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { 'employment.company_id': ['The selected company is invalid.'] },
        },
      } as never),
    )

    render(
      <UserForm mode={{ type: 'edit', user: userWithEmployment() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The selected company is invalid.')).toBeInTheDocument(),
    )
    expect(updateUserMock).toHaveBeenCalledTimes(1)
  })
})

describe('UserForm — employment i18n (spec 0015 AC-019)', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('it')
  })
  afterAll(async () => {
    await i18n.changeLanguage('en')
  })

  it('localizes the employment section headings and the relationship_type enum from the `enums` namespace', () => {
    render(
      <UserForm mode={{ type: 'edit', user: userWithEmployment() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByText('Rapporto contrattuale')).toBeInTheDocument()

    // relationship_type: 'employee' -> localized Italian label, no hardcoded string
    // in the JSX. The Select trigger is queried directly: Radix mirrors the value
    // into a hidden native <option>, so a bare text search would be ambiguous.
    expect(
      screen.getByRole('combobox', { name: 'Tipo di rapporto' }),
    ).toHaveTextContent('Dipendente')
  })
})
