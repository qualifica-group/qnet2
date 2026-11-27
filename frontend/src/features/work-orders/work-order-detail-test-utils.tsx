import type { ReactElement, ReactNode } from 'react'
import { render as rtlRender } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmContext } from '@/components/confirm-dialog-context'
import { WORKFLOW_STATUS_FIELDS } from '@/features/work-orders/work-order-workflow-fixtures'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Every render goes through a Router (the card links related records with
 * real `<Link>`s), a query client (the in-place editors share the edit
 * form, spec 0195 applied to Commesse) and the confirm service (the actions
 * menu's "Riapri" asks first).
 */
export function renderDetail(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <MemoryRouter>
        <QueryClientProvider client={client}>
          <ConfirmContext.Provider value={() => Promise.resolve(true)}>{children}</ConfirmContext.Provider>
        </QueryClientProvider>
      </MemoryRouter>
    )
  }
  return rtlRender(ui, { wrapper: Wrapper })
}

/** The work order the detail tests render, overridable per case. */
export function detailWorkOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    title_is_manual: true,
    type: 'processing',
    status: { value: 'to_plan', is_force_closed: false },
    completion_percentage: 0,
    is_force_closed: false,
    ...WORKFLOW_STATUS_FIELDS,
    force_close_reason: null,
    open_tasks_count: 0,
    callback_date: '2026-09-30',
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    description: 'Descrizione libera',
    is_multi_quote: false,
    source_quotes: [],
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    contract: { id: 9, code: 'QUO-0004', title: 'Fornitura annuale', expiry_date: '2027-03-31' },
    task_template: null,
    quote_lines: [
      { id: 11, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' }, registry: null },
      { id: 12, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' }, registry: null },
    ],
    applicable_attributes: [],
    attribute_layout: null,
    locked_attributes: [],
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      // Spec 0403: the full contract data needs view_costs and view_contract_data.
      actions: { view_activity: false, view_costs: true },
    },
    ...overrides,
  }
}
