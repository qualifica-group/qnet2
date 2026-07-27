import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ReferentForm } from '@/features/referents/referent-form'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'
import type { ResourceMeta, ResourcePermissions } from '@/features/authorization/types'
import type { CustomFieldDescriptor } from '@/features/custom-fields/types'
import type { PersonalDataCard } from '@/features/personal-data/types'

/**
 * Spec 0021: wiring the universal custom-fields renderer into the Referents
 * form via the SAME toolbox as Companies (the pilot) — mounting
 * `<CustomFieldsSection>` is the only referents-specific integration. This
 * suite exercises the wiring (rendering + create payload) without touching
 * the section's own per-type rendering (covered by `CustomFieldsSection.test.tsx`).
 */

const createReferentMock = vi.fn()
const updateReferentMock = vi.fn()

vi.mock('@/features/referents/api', () => ({
  createReferent: (...args: unknown[]) => createReferentMock(...args),
  updateReferent: (...args: unknown[]) => updateReferentMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ value }: { value: number | null }) => (
    <div data-testid="referent-type-value">{value ?? ''}</div>
  ),
}))

const FULL_ACCESS: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

const SPONSOR_LEVEL_FIELD: CustomFieldDescriptor = {
  key: 'custom.sponsor_level',
  type: 'text',
  label: 'Sponsor level',
  group: null,
  mandatory: false,
  source: 'custom',
}

function permissionsWithSponsorLevel(): ResourcePermissions {
  return {
    resource: FULL_ACCESS,
    fields: {
      'custom.sponsor_level': {
        visible: true,
        hidden: false,
        editable: true,
        readonly: false,
        required: false,
        disabled: false,
      },
    },
    actions: {},
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

function card(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
  return {
    id: 99,
    type: 'individual',
    first_name: 'Ada',
    last_name: 'Lovelace',
    company_name: null,
    full_name: 'Ada Lovelace',
    ceo: null,
    gender: null,
    tax_code: null,
    vat_number: null,
    sdi_code: null,
    birth_date: null,
    birth_city_id: null,
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
    permissions: permissionsWithSponsorLevel(),
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
  fetchResourceMetaMock.mockResolvedValue({
    fields: [SPONSOR_LEVEL_FIELD],
    permissions: permissionsWithSponsorLevel(),
  })
})

describe('ReferentForm — custom fields (spec 0021)', () => {
  it('renders the resource custom field control on the Account tab in create mode', async () => {
    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /^Account/ }))

    expect(await screen.findByRole('textbox', { name: 'Sponsor level' })).toBeInTheDocument()
  })

  it('includes the valued custom field in the create payload', async () => {
    createReferentMock.mockResolvedValue(referent())
    const onSuccess = vi.fn()

    render(
      <ReferentForm mode={{ type: 'create' }} onSuccess={onSuccess} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: 'Ada' } })
    fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: 'Lovelace' } })

    fireEvent.mouseDown(screen.getByRole('tab', { name: /^Account/ }))
    fireEvent.change(await screen.findByRole('textbox', { name: 'Sponsor level' }), {
      target: { value: 'Gold' },
    })

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createReferentMock).toHaveBeenCalledTimes(1))
    const payload = createReferentMock.mock.calls[0][0]
    expect(payload.custom_fields).toEqual({ sponsor_level: 'Gold' })
  })

  it('seeds the custom field value from the loaded referent detail in edit mode', async () => {
    render(
      <ReferentForm
        mode={{ type: 'edit', referent: referent({ custom_fields: { sponsor_level: 'Platinum' } }) }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.mouseDown(await screen.findByRole('tab', { name: /^Account/ }))

    expect(await screen.findByRole('textbox', { name: 'Sponsor level' })).toHaveValue('Platinum')
  })
})
