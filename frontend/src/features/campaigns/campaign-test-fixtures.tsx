import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { CampaignDetailWithPermissions } from '@/features/campaigns/types'

/** Shared fixtures of the campaign create/detail suites (spec 0221). */

/** The project every Project pick selects (the picker stub's id for its own trigger). */
export const TEST_PROJECT_ID = 42

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

export function campaignFixture(overrides: Partial<CampaignDetailWithPermissions> = {}): CampaignDetailWithPermissions {
  return {
    id: 9,
    code: 'CMP-0009',
    project_id: null,
    project: null,
    name: 'Spring push',
    description: null,
    partner_id: null,
    partner: null,
    operational_site_id: null,
    operational_site: null,
    source_id: null,
    source: null,
    derived_from_project: false,
    pipeline_status_id: 1,
    pipeline_status: { id: 1, name: 'Active', color: 'blue' },
    country_id: 61,
    country: { id: 61, name: 'Italy' },
    state_id: null,
    state: null,
    province_id: null,
    province: null,
    city_id: null,
    city: null,
    geo_scope: 'country',
    geo_locked_levels: [],
    product_lines: [{ id: 1, business_function: { id: 2, name: 'Sales' }, product_category: { id: 4, name: 'Hardware' } }],
    start_date: '2026-01-01',
    end_date: '2026-12-31',
    total_budget: null,
    target_lead: null,
    created_at: '2026-01-01T00:00:00Z',
    permissions: permissionsFor({ code: READ_ONLY }),
    ...overrides,
  }
}

/** A campaign reading through project 42, which fills the country (BR-2/BR-5). */
export function linkedCampaignFixture(overrides: Partial<CampaignDetailWithPermissions> = {}) {
  return campaignFixture({
    project_id: TEST_PROJECT_ID,
    project: { id: TEST_PROJECT_ID, code: 'PRJ-0042', name: 'Acme rollout' },
    derived_from_project: true,
    geo_locked_levels: ['country'],
    ...overrides,
  })
}

/** The `for-select` item of project 42, its `meta` the cascade a pick prefills (AC-042, BR-5). */
export function projectForSelectItem() {
  return {
    id: TEST_PROJECT_ID,
    label: 'PRJ-0042 — Acme rollout',
    meta: {
      partner: { id: 31, label: 'Jane Partner' },
      pipeline_status: { id: 41, label: 'Active' },
      state: null,
      product_lines: [{ business_function: { id: 51, name: 'Marketing' }, product_category: { id: 71, name: 'Hardware' } }],
      total_budget: '1000.00',
      allocated_budget: '600.00',
      remaining_budget: '400.00',
      operational_site: { id: 81, label: 'Warehouse A' },
      start_date: '2026-03-15',
      geo: { country: { id: 61, name: 'Italy' }, state: null, province: null, city: null },
    },
  }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function campaignWrapper() {
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

/** Fills the open editor's first product line (root, then category 4). */
export function pickProductLine() {
  fireEvent.click(screen.getByTestId('select-Parent category 1'))
  fireEvent.click(screen.getByTestId('select-Product category 1'))
}

/** The create rows a standalone campaign needs besides its code: name, country, one product line, start date. */
export function completeRequiredCreateRows() {
  typeInRow('Name', 'Spring push')
  clickInRow('Geography', 'geo-select')
  fireEvent.click(pencilOf('Product lines'))
  pickProductLine()
  confirmOpenRow('Done')
  typeInRow('Start date', '2026-01-01')
}
