import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { ProjectDetailWithPermissions } from '@/features/projects/types'

/** Shared fixtures of the project create/detail suites (spec 0220). */

export const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

export const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

/** A permissions block with every listed field at its permission (unlisted = visible and editable). */
export function permissionsFor(fields: Record<string, FieldPermission> = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields,
    actions: {},
  }
}

export function projectFixture(overrides: Partial<ProjectDetailWithPermissions> = {}): ProjectDetailWithPermissions {
  return {
    id: 7,
    code: 'PRJ-0007',
    name: 'Acme rollout',
    description: null,
    pipeline_status_id: 3,
    pipeline_status: { id: 3, name: 'Active', color: 'blue' },
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Widgets' } }],
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    allocated_budget: '0.00',
    remaining_budget: null,
    campaigns_count: 0,
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsFor({ code: READ_ONLY }),
    ...overrides,
  }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function projectWrapper() {
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

/** The text/number/date control labelled `field` (a field hint carries the same label on its own button). */
export function inputOf(field: string): HTMLElement {
  const control = screen
    .getAllByLabelText(new RegExp(`^${field}`))
    .find((element) => element.tagName === 'INPUT' || element.tagName === 'TEXTAREA')
  if (!control) {
    throw new Error(`No input labelled ${field}`)
  }
  return control
}

/** Opens a text/number/date row, types `value`, keeps it with `confirm`. */
export function typeInRow(field: string, value: string, confirm: 'Done' | 'Save' = 'Done') {
  fireEvent.click(pencilOf(field))
  fireEvent.change(inputOf(field), { target: { value } })
  confirmOpenRow(confirm)
}

/** Opens a row whose control is a stub button (`testId`), clicks it, keeps it with `confirm`. */
export function clickInRow(field: string, testId: string, confirm: 'Done' | 'Save' = 'Done') {
  fireEvent.click(pencilOf(field))
  fireEvent.click(screen.getByTestId(testId))
  confirmOpenRow(confirm)
}

/** The create rows a project needs besides its code: name, status, country, one product line, start date. */
export function completeRequiredCreateRows() {
  typeInRow('Name', 'Acme rollout')
  clickInRow('Status', 'select-Status')
  clickInRow('Geography', 'geo-select')
  fireEvent.click(pencilOf('Product lines'))
  fireEvent.click(screen.getByTestId('select-Parent category 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
  confirmOpenRow('Done')
  typeInRow('Start date', '2026-01-01')
}
