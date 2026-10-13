import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { CompanySiteDetailWithPermissions } from '@/features/company-sites/types'

/** Shared fixtures of the company-site detail/create suites (wave 3 of the "no edit pages" plan). */

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

/** QueryClient (fresh per test) + router, as the screens get them. */
export function companySiteWrapper() {
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

export function companySiteFixture(
  overrides: Partial<CompanySiteDetailWithPermissions> = {},
): CompanySiteDetailWithPermissions {
  return {
    id: 7,
    name: 'Sede Nord',
    notes: 'Main office',
    is_default: false,
    logo_url: null,
    personal_data: {
      id: 99,
      type: 'company',
      first_name: null,
      last_name: null,
      company_name: 'ACME S.p.A.',
      full_name: 'ACME S.p.A.',
      ceo: null,
      tax_code: null,
      vat_number: null,
      sdi_code: null,
      birth_date: null,
      birth_city_id: null,
      residence_city_id: null,
      gender: null,
      personable_type: 'company_site',
      personable_id: 7,
      contacts: [],
      addresses: [],
      created_at: null,
    },
    banks: [{ id: 5, name: 'Intesa', iban: 'IT60X0542811101000000123456', notes: null, is_primary: true }],
    company: { id: 3, label: 'Holding Srl' },
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
