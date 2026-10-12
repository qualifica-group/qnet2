import type { ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { fireEvent, screen } from '@testing-library/react'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import type { FieldPermission, ResourcePermissions } from '@/features/authorization/types'
import type { ForSelectItem } from '@/features/for-select/types'
import type { LeadDetailWithPermissions } from '@/features/leads/types'

/** Shared fixtures and picker stubs of the lead create/detail suites (spec 0217). */

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

/** A permissions block with every listed field at its permission (unlisted = visible and editable). */
export function permissionsFor(fields: Record<string, FieldPermission> = {}): ResourcePermissions {
  return {
    resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
    fields,
    actions: {},
  }
}

export function leadFixture(overrides: Partial<LeadDetailWithPermissions> = {}): LeadDetailWithPermissions {
  return {
    id: 9,
    registry_id: 10,
    registry: { id: 10, name: 'Mario Rossi' },
    campaign_id: 20,
    campaign: { id: 20, code: 'CMP-0001', name: 'Spring push' },
    lead_status: 'not_associated',
    operational_site_id: null,
    operational_site: null,
    source_id: 1,
    source: { id: 1, name: 'Web' },
    operator_id: null,
    operator: null,
    notes: 'Original note.',
    extra_fields: null,
    created_at: '2026-01-01T00:00:00Z',
    updated_at: '2026-01-01T00:00:00Z',
    permissions: permissionsFor(),
    ...overrides,
  }
}

/** QueryClient (fresh per test) + router + confirm dialog, as the screens get them. */
export function leadWrapper() {
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

/** Opens the row labelled `field`, clicks its picker stub (`select-<trigger label>`), keeps it with `confirm`. */
export function pickInRow(field: string, trigger: string, confirm: 'Done' | 'Save' = 'Done') {
  fireEvent.click(pencilOf(field))
  fireEvent.click(screen.getByTestId(`select-${trigger}`))
  confirmOpenRow(confirm)
}

/** The create form's header Save (the footer repeats it). */
export function clickCreateSave() {
  fireEvent.click(screen.getAllByRole('button', { name: 'Save' })[0])
}

/** A campaign with categories but no Sede/Fonte of its own: picking it prefills nothing. */
export const CAMPAIGN_PLAIN = {
  id: 20,
  label: 'Spring push',
  subtitle: 'CMP-0001',
  meta: { operational_site: null, product_category_ids: [10, 20] },
} as ForSelectItem

/** A campaign carrying its Sede and Fonte (`meta`), driving the prefill chain. */
export const CAMPAIGN_PREFILL = {
  id: 21,
  label: 'Summer push',
  subtitle: 'CMP-0002',
  meta: {
    operational_site: { id: 55, label: 'Warehouse A' },
    source: { id: 77, name: 'Referral' },
    product_category_ids: [10, 20],
  },
} as ForSelectItem

/** A campaign covering another category only: product 501 (category 10) falls outside it. */
export const CAMPAIGN_OTHER = {
  id: 30,
  label: 'Autumn push',
  subtitle: 'CMP-0003',
  meta: { operational_site: null, product_category_ids: [99] },
} as ForSelectItem

export const SITE_A: ForSelectItem = { id: 77, label: 'Warehouse A' }
export const SITE_B: ForSelectItem = { id: 88, label: 'Warehouse B' }

/** An Operatore with an employment Sede (meta), driving the AC-061 auto-fill. */
export const OPERATOR_WITH_SITE = {
  id: 5,
  label: 'Ada Lovelace',
  meta: { operational_site_id: 66, operational_site_label: 'Depot Z' },
} as ForSelectItem

/** An Operatore with no employment Sede: it must leave the Sede untouched. */
export const OPERATOR_NO_SITE: ForSelectItem = { id: 6, label: 'Bob Noyce' }

export const PRODUCT_501 = { id: 501, label: 'Widget', meta: { category_id: 10 } } as ForSelectItem

/** The campaigns the Campaign stub cycles through, in order; a suite may swap them in its `beforeEach`. */
export const stubState: { campaigns: ForSelectItem[] } = { campaigns: [CAMPAIGN_PLAIN, CAMPAIGN_OTHER] }
