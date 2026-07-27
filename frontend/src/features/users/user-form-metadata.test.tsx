import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Acceptance criteria 11-16 (spec 0004): the metadata-driven behaviour of the
 * form (hidden fields, readonly/disabled fields, required labels, gated
 * actions, edit-seeded permissions + 422 mapping, graceful fallback). The
 * users-CRUD behaviour itself (payload shaping, personal-data buffering, …)
 * is covered by `user-form.test.tsx`.
 */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()
const uploadUserAvatarMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: (...args: unknown[]) => uploadUserAvatarMock(...args),
  deleteUserAvatar: vi.fn(),
}))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// This suite isn't about the quick-create "+" (spec 0028): every relation
// field's `QuickCreateButton` reads `useAbilities()` via `Can`, which needs
// an `AuthProvider` this suite doesn't render.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
}))

const localeOptions: EnumOption[] = [
  { value: 'en', label: 'English', color: null, icon: null, is_default: true, hidden_on_form: false },
  { value: 'it', label: 'Italiano', color: null, icon: null, is_default: false, hidden_on_form: false },
]

vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => localeOptions,
}))

const validCard: PersonalDataCard = {
  id: 99,
  type: 'individual',
  gender: null,
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
  personable_type: 'user',
  personable_id: 7,
  contacts: [],
  addresses: [],
  created_at: null,
}

vi.mock('@/features/personal-data/use-personal-data', () => ({
  usePersonalDataByOwner: () => ({
    data: validCard,
    isPending: false,
    isError: false,
    refetch: vi.fn(),
  }),
}))

/** The `<label>` element whose text starts with `text` (exact-match helper). */
function labelFor(text: string): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith(text) === true,
  )
}

/**
 * Switches the active tab (spec 0015 tabbed redesign). Radix `TabsTrigger`
 * activates on `mouseDown` (and focus, in automatic mode) rather than
 * `click` — see `@radix-ui/react-tabs`.
 */
function switchTab(name: string) {
  // Match by name prefix: a macro tab with a validation error carries an extra
  // indicator in its accessible name, so an exact match would miss it.
  fireEvent.mouseDown(screen.getByRole('tab', { name: new RegExp(`^${name}`) }))
}

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
    avatar_url: 'https://example.test/avatar.png',
    created_at: null,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { upload_avatar: true, delete_avatar: true },
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  uploadUserAvatarMock.mockReset()
  fetchResourceMetaMock.mockReset()
  // `useCustomFieldsForm` (spec 0021) also reads this same endpoint (for the
  // dynamic custom-fields schema); default to none defined so it never
  // interferes with the metadata assertions below (edit-mode tests never
  // override this).
  fetchResourceMetaMock.mockResolvedValue({
    fields: [],
    permissions: { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  })
})

describe('UserForm — metadata-driven authorization (spec 0004)', () => {
  it('AC11/12/13 — hides a hidden field, disables a readonly field, marks a required field', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          email: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          roles: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          password: {
            visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // Email/password live under the Account macro tab (spec 0015 redesign).
    await waitFor(() => expect(screen.getByRole('tab', { name: /^Account/ })).toBeInTheDocument())
    switchTab('Account')

    // AC11: the hidden field is absent from the DOM.
    expect(screen.getByLabelText(/^Email/)).toBeInTheDocument()
    expect(screen.queryByLabelText(/Roles/)).not.toBeInTheDocument()

    // AC12: the readonly/non-editable field renders disabled.
    expect(screen.getByLabelText(/^Password/)).toBeDisabled()

    // AC13: `required` from metadata drives the label's `*` — email is
    // required, password is not (other required markers on screen belong to
    // the unrelated personal-data card, so the check is scoped per label).
    expect(labelFor('Email').textContent).toContain('*')
    expect(labelFor('Password').textContent).not.toContain('*')
  })

  it('AC16 — falls back to visible+editable when a field is missing from metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          email: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          // `password` intentionally absent from the metadata.
        },
        actions: {},
      },
    })

    render(
      <UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByRole('tab', { name: /^Account/ })).toBeInTheDocument())
    switchTab('Account')

    expect(screen.getByLabelText(/^Email/)).toBeInTheDocument()
    // No crash, and the field renders visible + editable (the graceful default).
    expect(screen.getByLabelText(/^Password/)).toBeEnabled()
  })

  it('AC14 — hides an action affordance gated off by metadata', () => {
    render(
      <UserForm
        mode={{ type: 'edit', user: user({ permissions: {
          resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
          fields: {},
          actions: { upload_avatar: false, delete_avatar: true },
        } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.queryByRole('button', { name: 'Choose image' })).not.toBeInTheDocument()
    // The un-gated action stays available (contrast: not everything is hidden).
    expect(screen.getByRole('button', { name: 'Remove' })).toBeInTheDocument()
  })

  it('AC15 — seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateUserMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { email: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <UserForm mode={{ type: 'edit', user: user() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    // The `email` 422 error renders inline in the Credentials tab: it must be
    // mounted for the message to appear (spec 0015 redesign unmounts inactive tabs).
    switchTab('Account')
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateUserMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
