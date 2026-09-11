import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'

/**
 * Integration coverage of spec 0037 through the real create form (AC-007,
 * AC-008, AC-009): the unit behaviour of the hook/component is covered by
 * `use-identity-duplicate-check.test.tsx` and `identity-duplicate-warning.test.tsx`;
 * this suite only asserts the wiring — typing a matching email surfaces the
 * warning and the save action stays enabled and functional.
 */

const createReferentMock = vi.fn()
const checkIdentityDuplicatesMock = vi.fn()

vi.mock('@/features/referents/api', () => ({
  createReferent: (...args: unknown[]) => createReferentMock(...args),
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

vi.mock('@/features/referents/use-referent-form-meta', () => ({
  useReferentFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
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
  referent_contact_scope: [
    { value: 'internal', label: 'Internal', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'external', label: 'External', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}

vi.mock('@/features/config/use-config', () => ({
  useConfig: () => ({ data: { enums } }),
  useEnumOptions: (key: string) => enums[key] ?? [],
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
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
  createReferentMock.mockReset()
  fetchResourceMetaMock.mockReset()
  fetchResourceMetaMock.mockResolvedValue({ fields: [], permissions: FULL_ACCESS_PERMISSIONS })
  checkIdentityDuplicatesMock.mockReset()
  checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
})

describe('ReferentForm — duplicate warning (spec 0037)', () => {
  it('does not check while the form is untouched (AC-008)', async () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await new Promise((resolve) => setTimeout(resolve, 350))
    expect(checkIdentityDuplicatesMock).not.toHaveBeenCalled()
    expect(screen.queryByRole('status')).not.toBeInTheDocument()
  })

  it('shows a non-blocking, role="status" warning after typing a matching email, and the save stays usable (AC-007, AC-009)', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'referent', owner_id: 9, name: 'Existing Referent', matched_on: ['email'] }],
    })
    createReferentMock.mockResolvedValue({
      id: 1,
      name: 'Ada Lovelace',
      referent_type_id: null,
      referent_type: null,
      contact_scope: 'internal',
      notes: null,
      personal_data: null,
      created_at: '2026-01-01T00:00:00Z',
    })
    const onSuccess = vi.fn()

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })
    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'duplicate@example.com' },
    })
    // Creating a referent requires a phone number (user directive 2026-07-31).
    fireEvent.change(screen.getByLabelText(/^Phone/), { target: { value: '+39 333 1234567' } })

    const status = await screen.findByRole('status')
    expect(status).toHaveTextContent('Referent Existing Referent might be a duplicate (email).')

    // It lives in the sticky SIDE column (user directive 2026-09-11): at the
    // foot of the form it was off-screen exactly while the operator was typing
    // the field that triggers it.
    expect(within(screen.getByRole('complementary')).getByRole('status')).toBe(status)

    const saveButton = within(screen.getByRole('banner')).getByRole('button', { name: 'Save' })
    expect(saveButton).not.toBeDisabled()

    fireEvent.click(saveButton)
    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('hides the warning again once the matching field is cleared (AC-008)', async () => {
    checkIdentityDuplicatesMock.mockResolvedValue({
      matches: [{ owner_type: 'referent', owner_id: 9, name: 'Existing Referent', matched_on: ['email'] }],
    })

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText('Email'), {
      target: { value: 'duplicate@example.com' },
    })
    await screen.findByRole('status')

    checkIdentityDuplicatesMock.mockResolvedValue({ matches: [] })
    fireEvent.change(screen.getByLabelText('Email'), { target: { value: '' } })

    await waitFor(() => expect(screen.queryByRole('status')).not.toBeInTheDocument())
  })
})
