import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDate } from '@/lib/formatting/date-display'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import { hasWorkOrderAction, selectWorkOrderAction } from '@/features/work-orders/work-order-actions-menu-test-utils'
import { detailWorkOrder as workOrder, renderDetail as render } from '@/features/work-orders/work-order-detail-test-utils'

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

// Related-record links and the collaboration card's Notes tab (`work-orders.view`)
// both read the actor's client abilities. Defaults to every ability granted so the
// existing field/link assertions stay unaffected; the collaboration describe block
// below overrides it to exercise gating.
const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

/**
 * Spec 0093 AC-075: number, title, type, calculated status, callback date,
 * contract no., linked offer, linked product lines, description
 * are all shown; the closure reason only when force-closed.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/work-order-contract-data/work-order-contract-data-section', () => ({
  WorkOrderContractDataSection: ({ canManage }: { canManage: boolean }) => (
    <div>contract-data-section manage:{String(canManage)}</div>
  ),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

// Spec 0134: the side collaboration card mounts notes and documents, which are
// not what these tests are about and would need a query client.
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: () => <div>notes-section</div>,
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: () => <div>documents-section</div>,
}))

vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: () => <div>task-board</div>,
}))

vi.mock('@/features/work-order-costs/work-order-costs-section', () => ({
  WorkOrderCostsSection: ({ canManage }: { canManage: boolean }) => <div>costs-section manage:{String(canManage)}</div>,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
  canMock.mockReset()
  canMock.mockImplementation((): boolean => true)
})

describe('WorkOrderDetailView — related records', () => {
  // User directive 2026-09-16: the field names the Contratto, not the offer underneath it.
  it('links the contract and every line product to their records', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('QUO-0004 — Fornitura annuale')).toBe('/contracts/9')
    expect(hrefOf('Consulenza')).toBe('/products/1')
    expect(hrefOf('Installazione')).toBe('/products/2')
  })

  it('shows the contract data section with view_contract_data, in place of the plain list', () => {
    const base = workOrder()
    render(
      <WorkOrderDetailView
        workOrder={workOrder({ permissions: { ...base.permissions, actions: { view_costs: true, view_contract_data: true, manage_payments: true } } })}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Work order contract data' })).toBeInTheDocument()
    expect(screen.getByText('contract-data-section manage:true')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Consulenza' })).not.toBeInTheDocument()
  })

  it('keeps the plain product list, links included, without view_contract_data', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('heading', { name: 'Work order contract data' })).toBeInTheDocument()
    expect(screen.queryByText(/contract-data-section/)).not.toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Consulenza' })).toHaveAttribute('href', '/products/1')
  })

  // REQUIREMENT CHANGED (spec 0403, user directive 2026-11-16): without view_costs each product still shows
  // its Title and Payment status, without any amount (spec 0380 showed nothing).
  it('shows only title and payment status of each product, no amounts, without view_costs', () => {
    const base = workOrder()
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          quote_lines: [
            {
              ...base.quote_lines[0],
              typology: { id: 2, code: 'institution', name: 'Ente', color: 'violet' },
              payment_status: { id: 3, name: 'Saldato', color: 'green', allows_delivery: true },
            },
            { ...base.quote_lines[1], payment_status: null },
          ],
          permissions: { ...base.permissions, actions: { view_contract_data: true } },
        })}
      />,
    )

    const section = screen.getByRole('heading', { name: 'Work order contract data' }).closest('section') as HTMLElement
    expect(screen.queryByText(/contract-data-section/)).not.toBeInTheDocument()
    expect(within(section).getByRole('link', { name: 'Consulenza' })).toHaveAttribute('href', '/products/1')
    expect(within(section).getByText('Saldato')).toBeInTheDocument()
    expect(within(section).getByText('No status')).toBeInTheDocument()
    expect(within(section).getAllByRole('term').map((term) => term.textContent)).not.toContain('Amount')
    const paymentTerms = within(section).getAllByText('Payment status')
    expect(paymentTerms).toHaveLength(2)
    // The typology badge sits on each payment status label; a line without one reads as consultancy.
    expect(paymentTerms.map((term) => term.textContent)).toEqual(['Payment statusConsulting', 'Payment statusEnte'])
  })

  // REQUIREMENT CHANGED (user directive 2026-11-27): no "Importo" row either; the contract data open on Start date.
  it('has neither the work order title, nor an amount, nor a work order payment status in the contract data (spec 0403)', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const section = screen.getByRole('heading', { name: 'Work order contract data' }).closest('section') as HTMLElement
    const terms = within(section).getAllByRole('term').map((term) => term.textContent)
    expect(terms[0]).toBe('Start date')
    expect(terms).not.toContain('Amount')
    expect(within(section).queryByText('Installazione impianto')).not.toBeInTheDocument()
    // The only payment statuses are the two products' own (each label carries the product typology badge).
    expect(terms.filter((term) => term?.startsWith('Payment status'))).toHaveLength(2)
  })

  it("links the client registry reached through the commessa's offer", () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          registry: { id: 7, name: 'Acme S.p.A.' },
          client_info: {
            legal_address: null,
            vat_number: null,
            tax_code: null,
            tags: [],
            emails: [],
            phones: [],
            referents: [],
            commercial: null,
            commercial_partner: null,
            sectors: [],
            comments: null,
          },
        })}
      />,
    )

    // REQUIREMENT CHANGED spec 0400: the row is "Company" of the "Client info" section.
    expect(screen.getByRole('heading', { level: 3, name: 'Client info' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'Acme S.p.A.' })).toHaveAttribute('href', '/registries/7')
  })

  // REQUIREMENT CHANGED (user directive 2026-11-27): the contract expiry left with the KPI strip.
  it('shows neither the contract expiry nor the contract no.', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByText('Contract expiry')).not.toBeInTheDocument()
    expect(screen.queryByText(formatDate('2027-03-31'))).not.toBeInTheDocument()
    expect(screen.queryByText('Contract no.')).not.toBeInTheDocument()
  })

  it('has no client info section when the commessa has no client registry', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ registry: null, client_info: null })} />)

    // REQUIREMENT CHANGED spec 0400: no client, no "Client info" section.
    expect(screen.queryByRole('heading', { level: 3, name: 'Client info' })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: 'Acme S.p.A.' })).not.toBeInTheDocument()
  })

  it("links the company and both sites of the commessa's offer, as the Contract detail does", () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          company: { id: 4, name: 'Qualifica Group S.r.l.' },
          company_site: { id: 5, name: 'Sede Napoli' },
          operational_site: { id: 6, label: 'Via Roma 1 - Napoli' },
        })}
      />,
    )

    expect(screen.getByText('Company references and business functions')).toBeInTheDocument()
    const hrefOf = (name: string) => screen.getByRole('link', { name }).getAttribute('href')
    expect(hrefOf('Qualifica Group S.r.l.')).toBe('/companies/4')
    expect(hrefOf('Sede Napoli')).toBe('/company-sites/5')
    expect(hrefOf('Via Roma 1 - Napoli')).toBe('/operational-sites/6')
  })

  it('lists the business functions of the opportunity product lines after the operational site, once each', () => {
    const line = (id: number, fn: string, category: string) => ({
      id,
      business_function: { id: id * 10, name: fn },
      product_category: { id: id * 100, name: category },
    })
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          product_lines: [line(1, 'Formazione', 'Corsi'), line(2, 'Consulenza', 'ISO'), line(3, 'Formazione', 'Webinar')],
        })}
      />,
    )

    const section = screen.getByRole('heading', { level: 3, name: 'Company references and business functions' }).closest('section') as HTMLElement
    const terms = within(section).getAllByRole('term').map((term) => term.textContent)
    expect(terms.slice(-2)).toEqual(['Operational site', 'Business function'])
    expect(within(section).getByText('Formazione, Consulenza')).toBeInTheDocument()
  })

  it('shows the company and sites rows with the empty placeholder when the offer has none', () => {
    render(<WorkOrderDetailView workOrder={workOrder({ company: null, company_site: null, operational_site: null })} />)

    expect(screen.getByText('Company')).toBeInTheDocument()
    expect(screen.getByText('Site')).toBeInTheDocument()
    expect(screen.getByText('Operational site')).toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Sede|Via Roma/ })).not.toBeInTheDocument()
  })
})

/**
 * Notes, documents and activity are tabs of the work order's tab card, each
 * gated by its own authorization source (spec 0134 D-3). REQUIREMENT CHANGED
 * (user directive 2026-11-16): they left the side column for the legacy-like
 * strip, where Info is always present and open by default.
 */
describe('WorkOrderDetailView — collaboration', () => {
  it('keeps only the Info tab when nothing else is authorized', () => {
    canMock.mockImplementation((): boolean => false)
    const base = workOrder()
    render(<WorkOrderDetailView workOrder={workOrder({ permissions: { ...base.permissions, actions: {} } })} />)

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual(['Info'])
  })

  it('shows the Notes tab, Info selected by default, when work-orders.view is granted', () => {
    canMock.mockImplementation((permission: string) => permission === 'work-orders.view')
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('aria-selected', 'false')
    expect(screen.getByRole('tab', { name: 'Info' })).toHaveAttribute('aria-selected', 'true')
    expect(screen.queryByRole('tab', { name: /documents/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
  })

  it("shows the Documents tab, reading the work order's own view_documents gate", () => {
    canMock.mockImplementation((): boolean => false)
    render(<WorkOrderDetailView workOrder={workOrder({ permissions: { ...workOrder().permissions, actions: { view_documents: true } } })} />)

    expect(screen.getByRole('tab', { name: 'Work order documents' })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()
  })

  it("shows the read-only Registry documents tab on the commessa's registry with registries.viewDocuments (spec 0173)", () => {
    canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
    render(<WorkOrderDetailView workOrder={workOrder({ registry: { id: 7, name: 'Acme S.p.A.' } })} />)

    expect(screen.getByRole('tab', { name: 'Registry documents' })).toBeInTheDocument()
  })

  it('omits the Registry documents tab when the payload carries no registry', () => {
    canMock.mockImplementation((permission: string) => permission === 'registries.viewDocuments')
    render(<WorkOrderDetailView workOrder={workOrder({ registry: null })} />)

    expect(screen.queryByRole('tab', { name: 'Registry documents' })).not.toBeInTheDocument()
  })

  it('mounts the Activity log section only inside the collaboration card, reading its own view_activity gate', () => {
    canMock.mockImplementation((): boolean => false)
    render(
      <WorkOrderDetailView
        workOrder={workOrder({ permissions: { ...workOrder().permissions, actions: { view_activity: true } } })}
      />,
    )

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Activity log' }))

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'work-orders', id: 4 })
  })

  it('hides the Activity log tab when view_activity is not granted', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByRole('tab', { name: 'Activity log' })).not.toBeInTheDocument()
    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })

  it('opens the Task board from its own tab', () => {
    canMock.mockImplementation((permission: string) => permission === 'tasks.viewAny')
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByText('task-board')).not.toBeInTheDocument()
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))
    expect(screen.getByText('task-board')).toBeInTheDocument()
  })
})

/**
 * Spec 0190 AC-011: the Costi section follows `permissions.actions.view_costs` alone.
 * REQUIREMENT CHANGED (user directive 2026-10-06): Costi is a tab next to the
 * Task board in one card, Task open by default, so the Costi tab is selected first.
 */
describe('WorkOrderDetailView — Costi section (spec 0190)', () => {
  function withActions(actions: Record<string, boolean>) {
    const base = workOrder()
    return workOrder({ permissions: { ...base.permissions, actions } })
  }

  function openCostsTab() {
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Costs' }))
  }

  it('is not rendered without view_costs', () => {
    render(<WorkOrderDetailView workOrder={withActions({ manage_costs: true })} />)

    expect(screen.queryByRole('tab', { name: 'Costs' })).not.toBeInTheDocument()
    expect(screen.queryByText(/costs-section/)).not.toBeInTheDocument()
  })

  it('is rendered read-only with view_costs alone', () => {
    render(<WorkOrderDetailView workOrder={withActions({ view_costs: true })} />)
    openCostsTab()

    expect(screen.getByText('costs-section manage:false')).toBeInTheDocument()
  })

  it('is editable with view_costs and manage_costs', () => {
    render(<WorkOrderDetailView workOrder={withActions({ view_costs: true, manage_costs: true })} />)
    openCostsTab()

    expect(screen.getByText('costs-section manage:true')).toBeInTheDocument()
  })
})

describe('WorkOrderDetailView — multi-offer work order (spec 0267 AC-016)', () => {
  const multi = () =>
    workOrder({
      is_multi_quote: true,
      source_quotes: [
        {
          id: 4,
          code: 'QUO-0004',
          title: 'Fornitura annuale',
          contract: { id: 9 },
          registry: { id: 1, name: 'Rossi S.r.l.' },
          company: { id: 2, name: 'Qualifica Group' },
        },
        {
          id: 5,
          code: 'QUO-0005',
          title: null,
          contract: null,
          registry: { id: 3, name: 'Bianchi S.p.A.' },
          company: null,
        },
      ],
      contract_number: null,
      quote: null,
      contract: null,
      registry: null,
      company: null,
      company_site: null,
      operational_site: null,
    })

  it('names the client on each product line instead of the contract and company sections', () => {
    render(
      <WorkOrderDetailView
        workOrder={{
          ...multi(),
          quote_lines: [
            { id: 11, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' }, registry: { id: 1, name: 'Rossi S.r.l.' } },
            { id: 12, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' }, registry: { id: 3, name: 'Bianchi S.p.A.' } },
          ],
        }}
      />,
    )

    // REQUIREMENT CHANGED (spec 0403): each product is a group of fields, no longer a list item.
    expect(within(screen.getByRole('group', { name: /PRD-0001/ })).getByRole('link', { name: 'Rossi S.r.l.' })).toBeInTheDocument()
    expect(within(screen.getByRole('group', { name: /PRD-0002/ })).getByRole('link', { name: 'Bianchi S.p.A.' })).toBeInTheDocument()
    expect(screen.getByText('Multi-offer')).toBeInTheDocument()
    // REQUIREMENT CHANGED spec 0331: label renamed with the shared section.
    expect(screen.queryByText('Registry')).not.toBeInTheDocument()
    expect(screen.queryByText('Company references and business functions')).not.toBeInTheDocument()
  })

  it('keeps the single-offer sections and no badge on a single-offer work order', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.queryByText('Multi-offer')).not.toBeInTheDocument()
  })
})

/**
 * Spec 0352 AC-014: the "Request update" action follows `permissions.actions.request_update` alone.
 * REQUIREMENT CHANGED (user directive 2026-11-27): an item of the "..." actions menu, no longer a header button.
 */
describe('WorkOrderDetailView — request update action (spec 0352)', () => {
  function withActions(actions: Record<string, boolean>) {
    const base = workOrder()
    return workOrder({ permissions: { ...base.permissions, actions } })
  }

  it('offers the action and opens the dialog with the request_update flag', () => {
    render(<WorkOrderDetailView workOrder={withActions({ request_update: true })} />)

    selectWorkOrderAction('Request update')

    expect(screen.getByRole('dialog', { name: 'Request update' })).toBeInTheDocument()
  })

  it('hides the action without the flag', () => {
    render(<WorkOrderDetailView workOrder={withActions({ request_update: false })} />)

    expect(hasWorkOrderAction('Request update')).toBe(false)
  })
})
