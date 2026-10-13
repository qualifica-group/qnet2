import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { OperationalSiteDetailWithPermissions } from '@/features/operational-sites/types'

/** Shared fixtures of the operational-site detail/create suites (wave 3 of the "no edit pages" plan). */

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
export function operationalSiteWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return {
    client,
    wrapper: ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>
        <MemoryRouter>{children}</MemoryRouter>
      </QueryClientProvider>
    ),
  }
}

export function operationalSiteFixture(
  overrides: Partial<OperationalSiteDetailWithPermissions> = {},
): OperationalSiteDetailWithPermissions {
  return {
    id: 9,
    alias: 'HQ',
    line1: 'Via Roma 1',
    postal_code: '20100',
    country_id: 1,
    country: { id: 1, name: 'Italy' },
    state_id: 2,
    region: { id: 2, name: 'Lombardy' },
    province_id: 3,
    province: { id: 3, name: 'Milan' },
    city_id: 4,
    city: { id: 4, name: 'Milan' },
    is_active: true,
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
