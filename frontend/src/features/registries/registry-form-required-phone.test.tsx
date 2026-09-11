import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RegistryForm } from '@/features/registries/registry-form'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'

/**
 * An anagrafica must be reachable by phone at creation (user directive
 * 2026-09-07), the rule the referenti already carried: the quick field is
 * marked required and the save is refused without a number. Client twin of
 * `StoreRegistryRequest` + `ValidatesRequiredPhoneContact`; the server side is
 * covered by `RegistryCrudTest`.
 */

const createRegistryMock = vi.fn()

vi.mock('@/features/registries/api', () => ({
  createRegistry: (...args: unknown[]) => createRegistryMock(...args),
  updateRegistry: vi.fn(),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

/** Not a metadata suite: every field resolves visible+editable (`fields` empty). */
const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/registries/use-registry-form-meta', () => ({
  useRegistryFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

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
  agreement_status: [],
  size_class: [],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: () => <div />,
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

/** Fills the card fields the save gate checks before it ever reaches the phone. */
function fillIdentity() {
  fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
  fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
})

describe('RegistryForm — phone required at creation (user directive 2026-09-07)', () => {
  it('marks the phone quick field as required in create mode, and only that one', () => {
    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.getByLabelText(/^Phone/)).toHaveAttribute('aria-required', 'true')
    expect(screen.getByLabelText('Email')).toHaveAttribute('aria-required', 'false')
    expect(screen.getByLabelText(/^Phone/).closest('div')?.textContent).toContain('*')
  })

  it('refuses the save when no phone number was entered', async () => {
    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fillIdentity()
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('Enter at least one phone number.')).toBeInTheDocument(),
    )
    expect(createRegistryMock).not.toHaveBeenCalled()
  })

  it('lets the save through once a number is entered', async () => {
    createRegistryMock.mockResolvedValue({ id: 1, name: 'Ada Lovelace' })

    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fillIdentity()
    fireEvent.change(screen.getByLabelText(/^Phone/), { target: { value: '+39 333 1234567' } })
    fireEvent.click(within(screen.getByRole('banner')).getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRegistryMock).toHaveBeenCalledTimes(1))
    const payload = createRegistryMock.mock.calls[0][0]
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.contacts[0].type).toBe('phone')
  })
})
