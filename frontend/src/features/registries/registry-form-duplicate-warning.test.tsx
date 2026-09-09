import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RegistryForm } from '@/features/registries/registry-form'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'

/**
 * The live duplicate panel, now on the anagrafica form too (user directive
 * 2026-09-09) and searching the whole identity namespace. Unit behaviour lives
 * in `identity-duplicates/*.test.tsx`; this suite asserts the wiring — a typed
 * codice fiscale reaches the check and its match is announced without gating
 * the save (the blocking gate is server-side).
 */

const createRegistryMock = vi.fn()
const checkIdentityDuplicatesMock = vi.fn()

vi.mock('@/features/registries/api', () => ({
  createRegistry: (...args: unknown[]) => createRegistryMock(...args),
  updateRegistry: vi.fn(),
  registryDetailQueryKey: (id: number | null) => ['registries', 'detail', id] as const,
}))

vi.mock('@/features/identity-duplicates/duplicate-check-api', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/identity-duplicates/duplicate-check-api')
  >('@/features/identity-duplicates/duplicate-check-api')
  return {
    ...actual,
    checkIdentityDuplicates: (...args: unknown[]) => checkIdentityDuplicatesMock(...args),
  }
})

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRegistryMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
  checkIdentityDuplicatesMock.mockReset()
  checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
})

describe('RegistryForm — duplicate warning (user directive 2026-09-09)', () => {
  it('does not check while the form is untouched', async () => {
    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkIdentityDuplicatesMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('announces a holder found ANYWHERE in the namespace when a tax code is typed', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'user', owner_id: 3, name: 'Ada Lovelace', matched_on: ['tax_code'] }],
    })

    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Tax code'), {
      target: { value: 'LVLDAA80A01H501V' },
    })

    const status = await screen.findByRole('status')
    expect(status).toHaveTextContent('User Ada Lovelace might be a duplicate (tax code).')
    expect(checkIdentityDuplicatesMock).toHaveBeenCalledWith({
      tax_code: 'LVLDAA80A01H501V',
      vat_number: undefined,
      contacts: undefined,
    })
    // Non-blocking: the panel never disables the save (server-side gate).
    expect(screen.getByRole('button', { name: 'Save' })).not.toBeDisabled()
  })

  it('hides the warning again once the matching field is cleared', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'registry', owner_id: 3, name: 'Acme Srl', matched_on: ['tax_code'] }],
    })

    render(
      <RegistryForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Tax code'), {
      target: { value: 'LVLDAA80A01H501V' },
    })
    await screen.findByRole('status')

    checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
    fireEvent.change(screen.getByLabelText('Tax code'), { target: { value: '' } })

    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
  })
})
