import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { PersonalDataCard } from '@/features/personal-data/types'
import type { ReferentDetailWithPermissions } from '@/features/referents/types'

/** Shared fixtures of the referente create/detail suites (spec 0216). */

export const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

export const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

export const HIDDEN: FieldPermission = { ...EDITABLE, visible: false, hidden: true, editable: false }

export const FULL_RESOURCE: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

/** A permissions block with every listed field at its permission (unlisted = visible and editable). */
export function permissionsFor(
  fields: Record<string, FieldPermission> = {},
  resource: ResourcePermissions['resource'] = FULL_RESOURCE,
): ResourcePermissions {
  return { resource, fields, actions: {} }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function referentWrapper() {
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

export function referentCard(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
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
    contacts: [
      {
        id: 5,
        type: 'email',
        label: 'Work',
        value: 'ada@work.com',
        is_primary: true,
        contactable_type: 'personal_data',
        contactable_id: 99,
        created_at: null,
      },
    ],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

export function referentFixture(overrides: Partial<ReferentDetailWithPermissions> = {}): ReferentDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    referent_type_id: 3,
    referent_type: { id: 3, name: 'Sponsor' },
    user_id: null,
    user: null,
    contact_scope: 'internal',
    notes: 'VIP sponsor',
    personal_data: referentCard(),
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsFor(),
    ...overrides,
  }
}

/** The pencil of the in-place row labelled `field`. */
export function pencilOf(field: string): HTMLElement {
  return screen.getByRole('button', { name: `Edit ${field}` })
}

/** The open row's confirm: "Done" on the create draft, "Save" on the detail. */
export function confirmOpenRow(label: 'Done' | 'Save') {
  fireEvent.click(screen.getByRole('button', { name: label }))
}

/** The create form's header Save (the footer repeats it). */
export function clickCreateSave() {
  fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])
}

/** Opens the create form's anagraphic card row, types an individual's names, keeps them with Done. */
export async function fillCardNames(firstName = 'Ada', lastName = 'Lovelace') {
  fireEvent.click(await screen.findByRole('button', { name: 'Edit Personal details' }))
  fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: firstName } })
  fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: lastName } })
  confirmOpenRow('Done')
}

/** Creating a referent requires a phone number (user directive 2026-07-31). */
export function fillRequiredPhone() {
  fireEvent.change(screen.getByLabelText(/^Phone/), { target: { value: '+39 333 1234567' } })
}
