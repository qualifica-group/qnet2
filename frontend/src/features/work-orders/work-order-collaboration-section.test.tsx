import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render as rtlRender, screen } from '@testing-library/react'
import type { ReactElement, ReactNode } from 'react'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { WorkOrderCollaborationSection } from '@/features/work-orders/work-order-collaboration-section'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/*
 * Collaboration card of the Commessa detail (spec 0134, D-3, AC-008).
 *
 * The three sections are agnostic components owned by other features: what
 * belongs to the Commessa is WHICH tab mounts and WITH WHICH props, so they are
 * stubbed down to their props. `TabsContent` renders unconditionally (same
 * idiom as `task-collaboration-section.test.tsx`): Radix switching is not what
 * is under test here.
 */

function render(ui: ReactElement) {
  return rtlRender(ui, { wrapper: MemoryRouter })
}

let granted: string[] = []

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => granted.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/ui/tabs', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui/tabs')>()
  return {
    ...actual,
    TabsContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  }
})

vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; showHeader?: boolean }) => (
    <div>{`notes-section:${props.entityType}:${props.entityId}:${String(props.showHeader)}`}</div>
  ),
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: { resource: string; id: number; canUpload: boolean; canDelete: boolean }) => (
    <div>{`documents-section:${props.resource}:${props.id}:${String(props.canUpload)}:${String(props.canDelete)}`}</div>
  ),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => (
    <div>{`activity-log-section:${props.resource}:${props.id}`}</div>
  ),
}))

vi.mock('@/features/work-orders/work-order-tasks-section', () => ({
  WorkOrderTasksSection: () => <div>tasks-section</div>,
}))

const label = (key: string) => i18n.t(key)

function workOrder(actions: Record<string, boolean>): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    is_force_closed: false,
    force_close_reason: null,
    callback_date: null,
    start_date: '2026-03-01',
    supervisors: [],
    participants: [],
    description: null,
    internal_notes: null,
    contract_number: null,
    quote: null,
    contract: null,
    task_template: null,
    quote_lines: [],
    applicable_attributes: [],
    attribute_layout: null,
    attribute_values: {},
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-01-01T09:00:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions,
    },
  }
}

const ALL_TABS = { view_documents: true, view_activity: true }

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  granted = []
})

describe('WorkOrderCollaborationSection — gating (AC-008)', () => {
  it('with every gate open shows Notes, Documents and Activity tabs', () => {
    granted = ['work-orders.view']
    render(<WorkOrderCollaborationSection workOrder={workOrder(ALL_TABS)} />)

    expect(screen.getAllByRole('tab').map((tab) => tab.textContent)).toEqual([
      label('notes.section.title'),
      label('attachments.title'),
      label('activityLog.title'),
    ])
  })

  it('with no gate open the whole card is absent', () => {
    render(<WorkOrderCollaborationSection workOrder={workOrder({})} />)

    expect(screen.queryByRole('tablist')).not.toBeInTheDocument()
    expect(screen.queryByText(/section:/)).not.toBeInTheDocument()
  })

  it('gates notes on work-orders.view alone and documents/activity on their server flags', () => {
    granted = ['work-orders.view']
    render(<WorkOrderCollaborationSection workOrder={workOrder({ view_documents: false, view_activity: false })} />)

    expect(screen.getAllByRole('tab')).toHaveLength(1)
    expect(screen.getByRole('tab', { name: label('notes.section.title') })).toBeInTheDocument()
  })
})

describe('WorkOrderCollaborationSection — props of the mounted sections', () => {
  it('passes the module slug to notes, the morph alias and attachment abilities to documents', () => {
    granted = ['work-orders.view', 'attachments.create']
    render(<WorkOrderCollaborationSection workOrder={workOrder(ALL_TABS)} />)

    expect(screen.getByText('notes-section:work-orders:4:false')).toBeInTheDocument()
    // `attachments.delete` was NOT granted: the two flags are read separately.
    expect(screen.getByText('documents-section:work_order:4:true:false')).toBeInTheDocument()
    expect(screen.getByText('activity-log-section:work-orders:4')).toBeInTheDocument()
  })
})

describe('WorkOrderDetailView — side column (spec 0134 D-3)', () => {
  it('mounts the activity log only inside the collaboration card', () => {
    granted = ['work-orders.view', 'tasks.viewAny']
    render(<WorkOrderDetailView workOrder={workOrder(ALL_TABS)} />)

    expect(screen.getAllByText('activity-log-section:work-orders:4')).toHaveLength(1)
    expect(screen.getByRole('tab', { name: label('activityLog.title') })).toBeInTheDocument()
    // The Task panel sits below the record, outside the collaboration card.
    expect(screen.getByText('tasks-section')).toBeInTheDocument()
  })

  it('renders no side card when no collaboration tab is authorized', () => {
    render(<WorkOrderDetailView workOrder={workOrder({})} />)

    expect(screen.queryByRole('tab', { name: label('notes.section.title') })).not.toBeInTheDocument()
    expect(screen.getByText('Installazione impianto')).toBeInTheDocument()
  })
})
