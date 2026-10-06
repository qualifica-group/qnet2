import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RegistryForm } from '@/features/registries/registry-form'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import { fillCardNames } from '@/features/registries/registry-test-fixtures'

/**
 * An anagrafica must be reachable by phone at creation (user directive
 * 2026-09-07), the rule the referenti already carried: the save is refused
 * without a number. Client twin of `StoreRegistryRequest` +
 * `ValidatesRequiredPhoneContact`; the server side is covered by
 * `RegistryCrudTest`. REQUIREMENT CHANGED (spec 0200, aligned with the
 * detail): the contacts are the detail's card ("Add contact"), no quick
 * fields, so no asterisk on a quick Phone field any more.
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

vi.mock('@/features/personal-data/contacts-manager', async () => ({
  ContactsManager: (await import('@/features/registries/registry-test-fixtures')).ContactsManagerStub,
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
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
  it('refuses the save when no phone number was entered', async () => {
    render(
      <RegistryForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await fillCardNames()
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() =>
      expect(screen.getByText('Enter at least one phone number.')).toBeInTheDocument(),
    )
    expect(createRegistryMock).not.toHaveBeenCalled()
  })

  it('lets the save through once a number is entered', async () => {
    createRegistryMock.mockResolvedValue({ id: 1, name: 'Ada Lovelace' })

    render(
      <RegistryForm onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await fillCardNames()
    fireEvent.click(screen.getByRole('button', { name: 'add-phone' }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])

    await waitFor(() => expect(createRegistryMock).toHaveBeenCalledTimes(1))
    const payload = createRegistryMock.mock.calls[0][0]
    expect(payload.personal_data.contacts).toHaveLength(1)
    expect(payload.personal_data.contacts[0].type).toBe('phone')
  })
})
