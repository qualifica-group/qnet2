import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { UserForm } from '@/features/users/user-form'
import type { UserDetailWithPermissions } from '@/features/users/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0021: wiring the universal custom-fields renderer into the Users form
 * via the SAME toolbox as Companies (the pilot) — mounting
 * `<CustomFieldsSection>` is the only users-specific integration. This suite
 * exercises the wiring (rendering + create payload); the section's own
 * per-type rendering is covered by `CustomFieldsSection.test.tsx`.
 */

const createUserMock = vi.fn()
const updateUserMock = vi.fn()

vi.mock('@/features/users/api', () => ({
  createUser: (...args: unknown[]) => createUserMock(...args),
  updateUser: (...args: unknown[]) => updateUserMock(...args),
  uploadUserAvatar: vi.fn(),
  deleteUserAvatar: vi.fn(),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

// This suite isn't about the quick-create "+" (spec 0028): the relation
// custom field's `QuickCreateButton` reads `useAbilities()` via `Can`, which
// needs an `AuthProvider` this suite doesn't render.
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => false, hasRole: () => false, roles: [], isLoading: false }),
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

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const PIPPO_FIELD: CustomFieldDescriptor = {
  key: 'custom.pippo',
  type: 'boolean',
  label: 'Pippo',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithPippo(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.pippo': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: { upload_avatar: true, delete_avatar: true },
  }
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
    avatar_url: null,
    created_at: null,
    permissions: permissionsWithPippo(),
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createUserMock.mockReset()
  updateUserMock.mockReset()
  personalDataData.mockReset()
  personalDataData.mockReturnValue(undefined)
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [PIPPO_FIELD], permissions: permissionsWithPippo() })
})

describe('UserForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control in create mode', async () => {
    render(
      <UserForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(await screen.findByRole('checkbox', { name: 'Pippo' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createUserMock.mockResolvedValue(user())
    const onSuccess = vi.fn()

    render(
      <UserForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })

    // The contacts block's quick "Email" shares the screen: scope to the
    // Authentication block.
    const authentication = screen.getByText('Authentication').closest('section') as HTMLElement
    fireEvent.change(within(authentication).getByLabelText(/^Email/), {
      target: { value: 'ada@example.com' },
    })
    fireEvent.change(screen.getByLabelText(/^Password/), { target: { value: 'secret123' } })
    fireEvent.change(screen.getByLabelText(/^Confirm password/), { target: { value: 'secret123' } })

    fireEvent.click(await screen.findByRole('checkbox', { name: 'Pippo' }))

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createUserMock).toHaveBeenCalledTimes(1))
    const payload = createUserMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ pippo: true })
  })

  it('seeds the custom field value from the loaded user detail in edit mode', async () => {
    render(
      <UserForm
        mode={{ type: 'edit', user: user({ custom_fields: { pippo: true } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )


    expect(await screen.findByRole('checkbox', { name: 'Pippo' })).toBeChecked()
  })
})
