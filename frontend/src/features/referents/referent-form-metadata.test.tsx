import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'
import type { ResourceMeta } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Acceptance criteria AC-020/AC-021 (spec 0016): the metadata-driven
 * behaviour of the referent form (hidden field absent, readonly field not
 * editable, required field marked, server 422 mapped inline — including the
 * `personal_data.*` paths, surfaced as an aggregated banner since that buffer
 * lives outside RHF, mirroring why `users` does not map them onto its own
 * outer form either).
 */

const createReferentMock = vi.fn()
const updateReferentMock = vi.fn()

vi.mock('@/features/referents/api', () => ({
  createReferent: (...args: unknown[]) => createReferentMock(...args),
  updateReferent: (...args: unknown[]) => updateReferentMock(...args),
  referentDetailQueryKey: (id: number | null) => ['referents', 'detail', id],
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

// Keyed by `resource`: the form mounts two of these pickers (referent-types,
// users, spec 0090 T-08), each gated by its own field permission.
vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ value, resource }: { value: number | null; resource: string }) => (
    <div data-testid={`${resource}-value`}>{value ?? ''}</div>
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
    contacts: [],
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
    referent_type_id: null,
    referent_type: null,
    contact_scope: 'internal',
    notes: null,
    personal_data: card(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
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
  // `useCustomFieldsForm` (spec 0021) also reads this same endpoint (for the
  // dynamic custom-fields schema); default to none defined so it never
  // interferes with the metadata assertions below (edit-mode tests never
  // override this).
  fetchResourceMetaMock.mockResolvedValue({
    fields: [],
    permissions: { resource: { view: true, create: true, update: true, delete: true, export: true, import: true }, fields: {}, actions: {} },
  })
})

describe('ReferentForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          referent_type_id: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          contact_scope: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          notes: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Notes/)).toBeInTheDocument())
    expect(screen.queryByTestId('referent-types-value')).not.toBeInTheDocument()
    expect(screen.getByText('Contact scope').closest('label')?.textContent).toContain('*')
  })

  it('hides the linked-user field independently by its own field permission (AC-005, spec 0090)', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          referent_type_id: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
          user_id: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
          contact_scope: {
            visible: true, hidden: false, editable: true, readonly: false, required: true, disabled: false,
          },
          notes: {
            visible: true, hidden: false, editable: true, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() => expect(screen.getByLabelText(/^Notes/)).toBeInTheDocument())
    expect(screen.getByTestId('referent-types-value')).toBeInTheDocument()
    expect(screen.queryByTestId('users-value')).not.toBeInTheDocument()
  })

  it('renders a readonly/non-editable field disabled in edit mode', async () => {
    render(
      <ReferentForm
        mode={{
          type: 'edit',
          referent: referent({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                notes: {
                  visible: true, hidden: false, editable: false, readonly: true, required: false, disabled: false,
                },
              },
              actions: {},
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const notes = screen.getByLabelText(/^Notes/)
    expect(notes).toBeDisabled()
    expect(notes).toHaveAttribute('readonly')
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateReferentMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { contact_scope: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <ReferentForm mode={{ type: 'edit', referent: referent() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateReferentMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })

  it('seeds the detail cache with the permissions envelope after a successful edit', async () => {
    // `updateReferent` resolves with a bare ReferentDetail (no `permissions`),
    // exactly like the real API client. The cache the detail page reads must
    // still carry `permissions`, or its `referent.permissions.resource` access
    // crashes on the next render.
    const bareSaved = referent({ name: 'Ada Byron' })
    delete (bareSaved as { permissions?: unknown }).permissions
    updateReferentMock.mockResolvedValue(bareSaved)

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <ConfirmDialogProvider>
          <ReferentForm mode={{ type: 'edit', referent: referent() }} onSuccess={vi.fn()} onCancel={vi.fn()} />
        </ConfirmDialogProvider>
      </QueryClientProvider>,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateReferentMock).toHaveBeenCalledTimes(1))

    const cached = client.getQueryData<ReferentDetailWithPermissions>(['referents', 'detail', 7])
    expect(cached?.permissions.resource.update).toBe(true)
    expect(cached?.name).toBe('Ada Byron')
  })

  it('surfaces a personal_data.* 422 error as a banner (buffer lives outside RHF)', async () => {
    updateReferentMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: {
            success: false,
            message: 'Validation failed',
            errors: { 'personal_data.first_name': ['The first name field is required.'] },
          },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <ReferentForm mode={{ type: 'edit', referent: referent() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(screen.getByText('The first name field is required.')).toBeInTheDocument(),
    )

    vi.restoreAllMocks()
  })
})
