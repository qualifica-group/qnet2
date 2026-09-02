import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { formatDateTime } from '@/features/table/cell-renderers'
import { formatDate } from '@/lib/formatting/date-display'
import { WorkOrderDetailView } from '@/features/work-orders/work-order-detail'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

/**
 * Spec 0093 AC-075: number, title, type, calculated status, callback date,
 * contract no., linked offer, linked product lines, description and notes
 * are all shown; the closure reason only when force-closed.
 */

const activityLogSectionMock = vi.fn()

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => {
    activityLogSectionMock(props)
    return <div>activity-log-section</div>
  },
}))

function workOrder(overrides: Partial<WorkOrderDetailWithPermissions> = {}): WorkOrderDetailWithPermissions {
  return {
    id: 4,
    code: 'COM-0001',
    title: 'Installazione impianto',
    type: 'processing',
    status: { value: 'open', is_force_closed: false },
    is_force_closed: false,
    force_close_reason: null,
    callback_date: '2026-09-30',
    start_date: '2026-03-01',
    supervisors: [{ id: 21, name: 'Ada Alberti' }],
    participants: [{ id: 31, name: 'Bruno Bianchi', position: 1 }],
    description: 'Descrizione libera',
    internal_notes: 'Nota interna',
    contract_number: 'QUO-0004',
    quote: { id: 4, code: 'QUO-0004', title: 'Fornitura annuale' },
    quote_lines: [
      { id: 11, sort_order: 2, product: { id: 2, code: 'PRD-0002', name: 'Installazione' } },
      { id: 12, sort_order: 1, product: { id: 1, code: 'PRD-0001', name: 'Consulenza' } },
    ],
    created_at: '2026-01-01T09:00:00Z',
    updated_at: '2026-02-15T14:30:00Z',
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: { view_activity: false },
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  activityLogSectionMock.mockReset()
})

describe('WorkOrderDetailView — detail fields (AC-075)', () => {
  it('shows number, title, type, status, callback date, contract no. and linked offer', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByRole('heading', { name: 'Installazione impianto' })).toBeInTheDocument()
    expect(screen.getByText('COM-0001')).toBeInTheDocument()
    expect(screen.getAllByText('Processing').length).toBeGreaterThan(0)
    expect(screen.getAllByText('Open').length).toBeGreaterThan(0)
    expect(screen.getByText(formatDate('2026-09-30'))).toBeInTheDocument()
    expect(screen.getByText('QUO-0004')).toBeInTheDocument()
    expect(screen.getByText('Fornitura annuale')).toBeInTheDocument()
  })

  it('lists the linked product lines ordered by sort_order', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    const lines = screen.getAllByText(/PRD-000\d/)
    expect(lines.map((el) => el.textContent)).toEqual(['PRD-0001', 'PRD-0002'])
    expect(screen.getByText('Consulenza')).toBeInTheDocument()
    expect(screen.getByText('Installazione')).toBeInTheDocument()
  })

  it('shows description, notes and both timestamps', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(screen.getByText('Descrizione libera')).toBeInTheDocument()
    expect(screen.getByText('Nota interna')).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-01-01T09:00:00Z'))).toBeInTheDocument()
    expect(screen.getByText(formatDateTime('2026-02-15T14:30:00Z'))).toBeInTheDocument()
  })

  it('shows the closure reason only when force-closed', () => {
    const { rerender } = render(<WorkOrderDetailView workOrder={workOrder()} />)
    expect(screen.queryByText('Force close reason')).not.toBeInTheDocument()

    rerender(
      <WorkOrderDetailView
        workOrder={workOrder({
          is_force_closed: true,
          force_close_reason: 'Cliente insolvente',
          status: { value: 'closed', is_force_closed: true },
        })}
      />,
    )

    expect(screen.getByText('Force close reason')).toBeInTheDocument()
    expect(screen.getByText('Cliente insolvente')).toBeInTheDocument()
    expect(screen.getAllByText('Closed').length).toBeGreaterThan(0)
  })
})

describe('WorkOrderDetailView — activity log section', () => {
  it('mounts the section when view_activity is granted', () => {
    render(
      <WorkOrderDetailView
        workOrder={workOrder({
          permissions: { ...workOrder().permissions, actions: { view_activity: true } },
        })}
      />,
    )

    expect(activityLogSectionMock).toHaveBeenCalledWith({ resource: 'work-orders', id: 4 })
  })

  it('hides the section when view_activity is not granted', () => {
    render(<WorkOrderDetailView workOrder={workOrder()} />)

    expect(activityLogSectionMock).not.toHaveBeenCalled()
  })
})
