/* eslint-disable react-refresh/only-export-components -- test doubles and helpers colocated on purpose */
import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { ContactDraft, PersonalDataCard } from '@/features/personal-data/types'
import type { RegistryDetailWithPermissions } from '@/features/registries/types'

/** Shared fixtures of the anagrafica create/detail suites (spec 0200). */

export const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

export const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

export const FULL_RESOURCE: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

/** A permissions block with every listed field at `permission`. */
export function permissionsFor(
  fields: Record<string, FieldPermission> = {},
  resource: ResourcePermissions['resource'] = FULL_RESOURCE,
): ResourcePermissions {
  return { resource, fields, actions: {} }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function registryWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    client,
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
        </MemoryRouter>
      </QueryClientProvider>
    ),
  }
}

export function registryCard(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
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
    gender: 'female',
    personable_type: 'registry',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

export function registryFixture(overrides: Partial<RegistryDetailWithPermissions> = {}): RegistryDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    source_id: null,
    source: null,
    sector_ids: [],
    sectors: [],
    referent_ids: [],
    referents: [],
    manager_ids: [],
    managers: [],
    manager_slots: [],
    supervisor_id: null,
    supervisor: null,
    commercial_id: null,
    commercial: null,
    reporter_id: null,
    reporter: null,
    vat_group: null,
    is_supplier: false,
    is_qualified_supplier: false,
    agreement_status: null,
    agreement_notes: null,
    size_class: null,
    employee_count: null,
    personal_data: registryCard(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsFor(),
    ...overrides,
  }
}

/** The pencil of the in-place row labelled `field`. */
export function pencilOf(field: string): HTMLElement {
  return screen.getByRole('button', { name: `Edit ${field}` })
}

/** The phone number the contacts stub adds. */
export const STUB_PHONE = '+39 333 1234567'

/**
 * Stand-in for `ContactsManager` (covered by its own suites): lists the
 * buffer and adds one phone contact, enough to drive the create gates.
 */
export function ContactsManagerStub({
  value,
  onChange,
}: {
  value: ContactDraft[]
  onChange: (next: ContactDraft[]) => void
}) {
  return (
    <div>
      {value.map((contact) => (
        <span key={contact._key}>{contact.value}</span>
      ))}
      <button
        type="button"
        onClick={() =>
          onChange([...value, { _key: 'stub-phone', type: 'phone', value: STUB_PHONE, label: null, is_primary: true }])
        }
      >
        add-phone
      </button>
    </div>
  )
}

/** Opens the create form's anagraphic card row, types an individual's names, keeps them with Done. */
export async function fillCardNames(firstName = 'Ada', lastName = 'Lovelace') {
  fireEvent.click(await screen.findByRole('button', { name: 'Edit Personal details' }))
  fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: firstName } })
  fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: lastName } })
  fireEvent.click(screen.getByRole('button', { name: 'Done' }))
}
