import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { CommissionConfigurationDetailWithPermissions } from './types'

/** Shared fixtures of the commission configuration detail/create suites (wave 4 of the "no edit pages" plan). */

export const EDITABLE: FieldPermission = {
  visible: true,
  hidden: false,
  editable: true,
  readonly: false,
  required: false,
  disabled: false,
}

export const HIDDEN: FieldPermission = { ...EDITABLE, visible: false, hidden: true, editable: false, disabled: true }

export const READ_ONLY: FieldPermission = { ...EDITABLE, editable: false, readonly: true }

export const FULL_RESOURCE: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: false,
}

export function permissionsFor(
  fields: Record<string, FieldPermission> = {},
  resource: ResourcePermissions['resource'] = FULL_RESOURCE,
  actions: ResourcePermissions['actions'] = {},
): ResourcePermissions {
  return { resource, fields, actions }
}

/** QueryClient (fresh per test) + router, as the screens get them. */
export function configurationWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <MemoryRouter>{children}</MemoryRouter>
    </QueryClientProvider>
  )
}

/** A COMMERCIAL product rule on a referent recipient, valid from a fixed date with no end. */
export function configurationFixture(
  overrides: Partial<CommissionConfigurationDetailWithPermissions> = {},
): CommissionConfigurationDetailWithPermissions {
  return {
    id: 1,
    name: 'Router rule',
    recipient_role: 'COMMERCIAL',
    application_scope: 'PRODUCT',
    product_category_id: null,
    product_category: null,
    product_id: 9,
    product: { id: 9, name: 'Router Pro' },
    recipient_type: 'referent',
    recipient_id: 5,
    recipient: { id: 5, name: 'Jane Referent' },
    commission_type: 'PERCENTAGE',
    value: '12.5000',
    priority: 3,
    valid_from: '2026-07-29',
    valid_until: null,
    status: 'ACTIVE',
    internal_note: null,
    created_at: '2026-07-29T10:00:00Z',
    updated_at: '2026-07-29T10:00:00Z',
    permissions: permissionsFor({}, FULL_RESOURCE, { view_activity: true }),
    ...overrides,
  }
}
