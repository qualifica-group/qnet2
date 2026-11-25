import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, fireEvent, screen, within } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { createPortal } from 'react-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter, useLocation } from 'react-router-dom'
import i18n from '@/i18n'
import { WorkOrderDetailTabs } from '@/features/work-orders/work-order-detail-tabs'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * The work order detail's tab card laid out like the legacy "Gestione
 * commessa" (user directive 2026-11-16): Info first and open, Task, Documenti
 * and Amministrazione as groups, Note, then Chat/Email/Attivita'; the title
 * "tab" set apart brings back Info. REQUIREMENT CHANGED: it replaces the
 * former Task/Costi/Proforma/Chat card (`WorkOrderDetailWorkTabs`) and the
 * collaboration side column.
 */

/** Renders inside a router at `url` (the list by default, i.e. the modal Sheet case); the probe prints the current URL. */
function render(ui: ReactElement, url = '/work-orders') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  function Wrapper({ children }: { children: ReactNode }) {
    return (
      <QueryClientProvider client={client}>
        <MemoryRouter initialEntries={[url]}>
          {children}
          <LocationProbe />
        </MemoryRouter>
      </QueryClientProvider>
    )
  }
  return rtlRender(ui, { wrapper: Wrapper })
}

function LocationProbe() {
  const location = useLocation()
  return <output aria-label="location">{`${location.pathname}${location.search}`}</output>
}

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: (permission: string) => canMock(permission) }),
}))

// The board's own header actions reach the strip through the slot it is handed.
vi.mock('@/features/work-orders/task-board/work-order-task-board', () => ({
  WorkOrderTaskBoard: ({ actionsContainer }: { actionsContainer: HTMLElement | null }) => (
    <>
      <div>task-board</div>
      {actionsContainer ? createPortal(<button type="button">board-action</button>, actionsContainer) : null}
    </>
  ),
}))

vi.mock('@/features/work-order-costs/work-order-costs-section', () => ({
  WorkOrderCostsSection: ({ canManage }: { canManage: boolean }) => <div>costs-section manage:{String(canManage)}</div>,
}))

vi.mock('@/features/work-order-invoices/work-order-invoices-section', () => ({
  WorkOrderInvoicesSection: ({ kind }: { kind: string }) => <div>invoices-section {kind}</div>,
}))

vi.mock('@/features/work-order-chat/work-order-chat-tab-label', () => ({
  WorkOrderChatTabLabel: () => <>Chat</>,
}))

vi.mock('@/features/work-order-chat/work-order-chat-panel', () => ({
  WorkOrderChatPanel: ({ canWriteAction }: { canWriteAction: boolean }) => <div>chat-panel write:{String(canWriteAction)}</div>,
}))

vi.mock('@/features/work-order-page/work-order-page-panel', () => ({
  WorkOrderPagePanel: ({ canEditAction }: { canEditAction: boolean }) => <div>page-panel edit:{String(canEditAction)}</div>,
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: ({ resource }: { resource: string }) => <div>documents-section {resource}</div>,
}))
vi.mock('@/features/attachments/documents-tab-count', () => ({ DocumentsTabCount: () => null }))
vi.mock('@/features/notes/notes-section', () => ({ NotesSection: () => <div>notes-section</div> }))
vi.mock('@/features/notes/notes-tab-count', () => ({ NotesTabCount: () => null }))
vi.mock('@/features/activity-log/activity-log-section', () => ({ ActivityLogSection: () => <div>activity-section</div> }))
vi.mock('@/features/outbound-emails/use-outbound-emails-tab', () => ({ useOutboundEmailsTab: () => null }))
vi.mock('@/features/document-requests/document-requests-section', () => ({
  DocumentRequestsSection: ({ workOrderId }: { workOrderId: number }) => <div>document-requests-section {workOrderId}</div>,
}))

function workOrder(actions: Record<string, boolean>, registry: { id: number; name: string } | null = null) {
  return {
    id: 4,
    title: 'Installazione impianto',
    registry,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions,
    },
  } as WorkOrderDetailWithPermissions
}

function renderTabs(actions: Record<string, boolean>, registry: { id: number; name: string } | null = null) {
  return render(<WorkOrderDetailTabs workOrder={workOrder(actions, registry)} info={<div>info-sections</div>} />)
}

/** Labels of the top strip only (group tabs host their own sub-strip). */
function topTabs(): string[] {
  const strip = screen.getByRole('tablist', { name: 'Work order sections' })
  return within(strip).getAllByRole('tab').map((tab) => tab.textContent?.trim() ?? '')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  // The document requests tab has its own gate (spec 0428): the legacy-order tests run without it.
  canMock.mockImplementation((permission) => !permission.startsWith('document-requests.'))
})

describe('WorkOrderDetailTabs', () => {
  it('opens Info by default, in the legacy order, the other contents not mounted', () => {
    renderTabs({ view_costs: true, view_documents: true, view_activity: true })

    expect(topTabs()).toEqual(['Info', 'Tasks', 'Work order documents', 'Costs', 'Notes', 'Activity log'])
    expect(screen.getByRole('tab', { name: 'Info' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('info-sections')).toBeInTheDocument()
    expect(screen.queryByText('task-board')).not.toBeInTheDocument()
  })

  it('has no Document requests tab without document-requests.viewAny (spec 0428)', () => {
    renderTabs({ view_documents: true })

    expect(topTabs()).toEqual(['Info', 'Tasks', 'Work order documents', 'Notes'])
  })

  it('adds Document requests to the Documents group with document-requests.viewAny (spec 0428)', () => {
    canMock.mockReturnValue(true)
    renderTabs({ view_documents: true })

    expect(topTabs()).toEqual(['Info', 'Tasks', 'Documents', 'Notes'])
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Documents' }))
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Document requests' }))
    expect(screen.getByText('document-requests-section 4')).toBeInTheDocument()
  })

  it('keeps Info alone when nothing else is authorized', () => {
    canMock.mockReturnValue(false)
    renderTabs({})

    expect(topTabs()).toEqual(['Info'])
  })

  it('shows the work order title set apart after the strip, bringing back Info', () => {
    renderTabs({ view_costs: true })

    const strip = screen.getByRole('tablist', { name: 'Work order sections' })
    const titleTab = screen.getByRole('button', { name: 'Installazione impianto' })
    expect(strip).not.toContainElement(titleTab)
    expect(strip.parentElement).toContainElement(titleTab)

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Costs' }))
    expect(screen.queryByText('info-sections')).not.toBeInTheDocument()

    fireEvent.click(titleTab)
    expect(screen.getByRole('tab', { name: 'Info' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('info-sections')).toBeInTheDocument()
  })

  it('portals the board actions into the strip while the Task tab is open', () => {
    renderTabs({})

    expect(screen.queryByRole('button', { name: 'board-action' })).not.toBeInTheDocument()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))

    const header = screen.getByRole('tablist', { name: 'Work order sections' }).parentElement as HTMLElement
    expect(within(header).getByRole('button', { name: 'board-action' })).toBeInTheDocument()
    expect(screen.getByText('task-board')).toBeInTheDocument()
  })

  it('groups costs and proforma under Administration (spec 0190, 0282 AC-007)', () => {
    renderTabs({ view_costs: true, manage_costs: true, view_invoices: true })

    expect(topTabs()).toEqual(['Info', 'Tasks', 'Administration', 'Notes'])

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Administration' }))

    expect(screen.getByRole('tab', { name: 'Costs' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('costs-section manage:true')).toBeInTheDocument()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Passive proforma' }))

    expect(screen.getByText('invoices-section passive')).toBeInTheDocument()
  })

  it('shows only the proforma under Administration without view_costs', () => {
    renderTabs({ view_invoices: true })

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Administration' }))

    expect(screen.queryByRole('tab', { name: 'Costs' })).not.toBeInTheDocument()
    expect(screen.getByRole('tab', { name: 'Active proforma' })).toHaveAttribute('data-state', 'active')
  })

  it('has no Administration tab without view_costs and view_invoices', () => {
    renderTabs({ view_contract_data: true })

    expect(topTabs()).not.toContain('Administration')
    expect(topTabs()).not.toContain('Costs')
  })

  it("groups the work order's and the registry's documents under Documents (spec 0173)", () => {
    renderTabs({ view_documents: true }, { id: 1, name: 'Acme' })

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Documents' }))

    expect(screen.getByRole('tab', { name: 'Work order documents' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('documents-section work_order')).toBeInTheDocument()

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Registry documents' }))

    expect(screen.getByText('documents-section registry')).toBeInTheDocument()
  })

  it('adds the Chat tab with view_chat and passes write_chat to the panel (spec 0359 AC-017)', () => {
    renderTabs({ view_chat: true, write_chat: true })

    expect(topTabs()).toContain('Chat')

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Chat' }))

    expect(screen.getByText('chat-panel write:true')).toBeInTheDocument()
  })

  it('has no Chat tab without view_chat (spec 0359 AC-017)', () => {
    renderTabs({ view_costs: true, write_chat: true })

    expect(screen.queryByRole('tab', { name: 'Chat' })).not.toBeInTheDocument()
  })

  it('adds the Workspace notes tab right after the Chat with view_page and passes edit_page (spec 0420 AC-001)', () => {
    renderTabs({ view_chat: true, view_page: true, edit_page: true })

    const tabs = topTabs()
    expect(tabs.indexOf('Workspace notes')).toBe(tabs.indexOf('Chat') + 1)

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Workspace notes' }))

    expect(screen.getByText('page-panel edit:true')).toBeInTheDocument()
  })

  it('has no Workspace notes tab without view_page (spec 0420 AC-001)', () => {
    renderTabs({ view_chat: true, edit_page: true })

    expect(screen.queryByRole('tab', { name: 'Workspace notes' })).not.toBeInTheDocument()
  })
})

describe('WorkOrderDetailTabs — tabs behave like pages', () => {
  const PAGE_URL = '/work-orders/4'

  it('writes the open tab in the URL of the work order page, and drops it for Info', () => {
    render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} />, PAGE_URL)

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))
    expect(screen.getByRole('status', { name: 'location' })).toHaveTextContent('/work-orders/4?tab=tasks')

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Info' }))
    expect(screen.getByRole('status', { name: 'location' })).toHaveTextContent(/^\/work-orders\/4$/)
  })

  it('opens the tab named in the URL, as after a reload', () => {
    render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} />, `${PAGE_URL}?tab=tasks`)

    expect(screen.getByRole('tab', { name: 'Tasks' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('task-board')).toBeInTheDocument()
  })

  it('opens Info on a fresh visit, whatever tab was open last time', () => {
    const { unmount } = render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} />, PAGE_URL)
    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))
    unmount()

    render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} />, PAGE_URL)

    expect(screen.getByRole('tab', { name: 'Info' })).toHaveAttribute('data-state', 'active')
  })

  it('falls back to Info when the URL names a tab the actor cannot see', () => {
    render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} />, `${PAGE_URL}?tab=costs`)

    expect(screen.getByRole('tab', { name: 'Info' })).toHaveAttribute('data-state', 'active')
  })

  it('opens the initial tab in the modal Sheet (spec 0405, the grid menu)', () => {
    render(<WorkOrderDetailTabs workOrder={workOrder({})} info={<div>info-sections</div>} initialTab="notes" />)

    expect(screen.getByRole('tab', { name: 'Notes' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('notes-section')).toBeInTheDocument()
  })

  it('gates the Notes tab of a project work order on project-work-orders.view (2026-11-25)', () => {
    canMock.mockImplementation((permission) => permission === 'work-orders.view')
    const project = { ...workOrder({}), type: 'project' } as WorkOrderDetailWithPermissions
    const { unmount } = render(<WorkOrderDetailTabs workOrder={project} info={<div>info-sections</div>} />)

    expect(screen.queryByRole('tab', { name: 'Notes' })).not.toBeInTheDocument()
    unmount()

    canMock.mockImplementation((permission) => permission === 'project-work-orders.view')
    render(<WorkOrderDetailTabs workOrder={project} info={<div>info-sections</div>} />)

    expect(screen.getByRole('tab', { name: 'Notes' })).toBeInTheDocument()
  })

  it('opens the group holding the requested sub-tab (spec 0405, Documenti)', () => {
    render(
      <WorkOrderDetailTabs
        workOrder={workOrder({ view_documents: true }, { id: 1, name: 'Acme' })}
        info={<div>info-sections</div>}
      />,
      `${PAGE_URL}?tab=documents`,
    )

    expect(screen.getByRole('tab', { name: 'Documents' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByText('documents-section work_order')).toBeInTheDocument()
  })

  it('keeps the tab off the URL outside the work order page (modal Sheet over the list)', () => {
    renderTabs({})

    fireEvent.mouseDown(screen.getByRole('tab', { name: 'Tasks' }))

    expect(screen.getByRole('tab', { name: 'Tasks' })).toHaveAttribute('data-state', 'active')
    expect(screen.getByRole('status', { name: 'location' })).toHaveTextContent(/^\/work-orders$/)
  })
})
