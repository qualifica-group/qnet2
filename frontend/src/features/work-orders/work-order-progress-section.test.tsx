import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import i18n from '@/i18n'
import { WorkOrderProgressSection } from '@/features/work-orders/work-order-progress-section'
import { detailWorkOrder } from '@/features/work-orders/work-order-detail-test-utils'

/** User directive 2026-11-27: "Avanzamento commessa", the legacy Info and "€" block (spec 0321). */

vi.mock('@/features/work-orders/work-order-progress-dialog', () => ({
  WorkOrderProgressDialog: ({ workOrderId }: { workOrderId: number | null }) =>
    workOrderId === null ? null : <div role="dialog">progress-dialog {workOrderId}</div>,
}))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

const section = () => screen.getByRole('heading', { level: 3, name: 'Work order progress' }).closest('section') as HTMLElement
const valueOf = (term: string) => within(section()).getByText(term, { selector: 'dt' }).nextElementSibling?.textContent

describe('WorkOrderProgressSection', () => {
  it('shows the total reached and the date of the last entry', () => {
    render(<WorkOrderProgressSection workOrder={detailWorkOrder({ completion_percentage: 60, completion_recorded_on: '2026-11-12' })} />)

    expect(valueOf('Total % reached')).toBe('60%')
    expect(valueOf('Last progress')).toBe('12/11/2026')
  })

  it('reads 0% with the empty placeholder when nothing is recorded', () => {
    render(<WorkOrderProgressSection workOrder={detailWorkOrder({ completion_percentage: 0, completion_recorded_on: null })} />)

    expect(valueOf('Total % reached')).toBe('0%')
    expect(valueOf('Last progress')).toBe('—')
  })

  it('opens the progress popup of this work order from "Manage progress"', () => {
    render(<WorkOrderProgressSection workOrder={detailWorkOrder()} />)

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    fireEvent.click(within(section()).getByRole('button', { name: 'Manage progress' }))

    expect(screen.getByRole('dialog')).toHaveTextContent('progress-dialog 4')
  })
})
