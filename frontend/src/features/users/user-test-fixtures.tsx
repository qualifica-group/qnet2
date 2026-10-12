import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { EnumOption } from '@/features/config/types'
import type { PersonalDataCard } from '@/features/personal-data/types'
import type { EmploymentDetail, UserDetailWithPermissions } from '@/features/users/types'

/** Shared fixtures of the user create/detail suites (spec 0222). */

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

export const REQUIRED: FieldPermission = { ...EDITABLE, required: true }

/** A permissions block with every listed field at its permission (unlisted = visible and editable). */
export function permissionsFor(
  fields: Record<string, FieldPermission> = {},
  actions: Record<string, boolean> = { upload_avatar: true, delete_avatar: true },
): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields,
    actions,
  }
}

/** The enums the user screens read (card type, contact type, locale). */
export const USER_ENUMS: Record<string, EnumOption[]> = {
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

export function userCard(overrides: Partial<PersonalDataCard> = {}): PersonalDataCard {
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
    personable_type: 'user',
    personable_id: 7,
    contacts: [],
    addresses: [],
    created_at: null,
    ...overrides,
  }
}

/** An employment profile with every field unset unless overridden. */
export function employmentFixture(overrides: Partial<EmploymentDetail> = {}): EmploymentDetail {
  return {
    id: 1,
    is_manager: false,
    job_description: null,
    relationship_type: null,
    qualification_type: null,
    hired_at: null,
    terminated_at: null,
    standard_daily_minutes: null,
    break_daily_minutes: null,
    reports_to_ids: [],
    company_id: null,
    primary_operational_site_id: null,
    remote_operational_site_ids: [],
    is_assignable: true,
    covers_all_product_categories: false,
    product_lines: [],
    reports_to: [],
    company: null,
    primary_operational_site: null,
    remote_operational_sites: [],
    ...overrides,
  }
}

export function userFixture(overrides: Partial<UserDetailWithPermissions> = {}): UserDetailWithPermissions {
  return {
    id: 7,
    name: 'Ada Lovelace',
    email: 'ada@example.com',
    locale: 'en',
    is_active: true,
    roles: [],
    avatar_url: null,
    must_set_password: false,
    personal_data: userCard(),
    employment: null,
    created_at: null,
    permissions: permissionsFor(),
    ...overrides,
  }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function userWrapper() {
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

/** The text/date control labelled `field` (a field hint carries the same label on its own button). */
export function inputOf(field: string): HTMLElement {
  const control = screen
    .getAllByLabelText(new RegExp(`^${field}`))
    .find((element) => element.tagName === 'INPUT' || element.tagName === 'TEXTAREA')
  if (!control) {
    throw new Error(`No input labelled ${field}`)
  }
  return control
}

/**
 * The open row's first text control: the row moves focus into its editor, so
 * the focused element disambiguates labels shared with another block (the
 * sign-in Email vs the contacts' quick Email).
 */
export function focusedInput(field: string): HTMLElement {
  const focused = document.activeElement
  return focused instanceof HTMLInputElement || focused instanceof HTMLTextAreaElement ? focused : inputOf(field)
}

/** Opens a text row, types `value`, keeps it with `confirm`. */
export function typeInRow(field: string, value: string, confirm: 'Done' | 'Save' = 'Done') {
  fireEvent.click(pencilOf(field))
  fireEvent.change(focusedInput(field), { target: { value } })
  confirmOpenRow(confirm)
}

/** Opens the anagraphic card row, types an individual's names, keeps them with `confirm`. */
export async function fillCardNames(confirm: 'Done' | 'Save' = 'Done', firstName = 'Ada', lastName = 'Lovelace') {
  fireEvent.click(await screen.findByRole('button', { name: 'Edit Personal details' }))
  fireEvent.change(await screen.findByLabelText(/^First name/), { target: { value: firstName } })
  fireEvent.change(screen.getByLabelText(/^Last name/), { target: { value: lastName } })
  confirmOpenRow(confirm)
}

/** The create rows a user needs: the card and the sign-in email. */
export async function fillRequiredCreateRows() {
  await fillCardNames()
  typeInRow('Email', 'ada@example.com')
}
