import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LineStatusDialog } from '@/features/purchase-requests/line-status-dialog'
import type { LineTarget } from '@/features/purchase-requests/line-status-transitions'
import type { LineStatusChangePayload, LineStatusChangeResult } from '@/features/purchase-requests/types'

const changeMock = vi.fn<(payload: LineStatusChangePayload) => Promise<LineStatusChangeResult>>()
vi.mock('@/features/purchase-requests/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/purchase-requests/api')>()),
  changeLinesStatus: (payload: LineStatusChangePayload) => changeMock(payload),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn(), info: vi.fn() } }))

function target(overrides: Partial<LineTarget> & Pick<LineTarget, 'id'>): LineTarget {
  return {
    status: null,
    transitions: [],
    capabilities: [],
    description: `Line ${overrides.id}`,
    quantity: 2,
    unitSymbol: 'pc',
    totalAmount: 1234.5,
    purchaseRequestId: 5,
    purchaseRequestSubject: 'Laptops',
    ...overrides,
  }
}

function renderDialog(targets: LineTarget[], onChanged = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <LineStatusDialog targets={targets} onOpenChange={vi.fn()} onChanged={onChanged} />
    </QueryClientProvider>,
  )
  return onChanged
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  changeMock.mockReset().mockResolvedValue({ updated_count: 2, closed_purchase_request_ids: [] })
})

describe('LineStatusDialog (AC-018, AC-022)', () => {
  it('summarizes the selected lines and offers only the transitions every line allows', async () => {
    const onChanged = renderDialog([
      target({
        id: 1,
        status: 'pending_approval',
        transitions: ['approved', 'rejected'],
        capabilities: ['approve', 'manage'],
      }),
      target({
        id: 2,
        status: 'approved',
        transitions: ['approved', 'on_hold'],
        capabilities: ['manage'],
        purchaseRequestId: 6,
      }),
    ])

    expect(screen.getByText('Action on 2 lines')).toBeInTheDocument()
    expect(screen.getByText('2 different RDA')).toBeInTheDocument()
    expect(screen.getByRole('table', { name: 'Selected lines' })).toBeInTheDocument()
    expect(screen.getByRole('cell', { name: 'Line 1' })).toBeInTheDocument()
    expect(screen.getAllByRole('cell', { name: '2 pc' })[0]).toBeInTheDocument()
    expect(screen.getByText('To approve')).toBeInTheDocument()
    expect(screen.getByText('You are acting as:')).toBeInTheDocument()
    expect(screen.getByText('Status management')).toBeInTheDocument()
    expect(screen.queryByText('Function manager')).not.toBeInTheDocument()
    expect(screen.getByText('Only the statuses reachable from all the selected lines are shown.')).toBeInTheDocument()
    expect(screen.getByText('The reason will be applied to all 2 selected lines.')).toBeInTheDocument()

    expect(screen.getByRole('radio', { name: 'Approved' })).toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'Rejected' })).not.toBeInTheDocument()
    expect(screen.queryByRole('radio', { name: 'On hold' })).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirm on 2 lines' })).toBeDisabled()

    fireEvent.click(screen.getByRole('radio', { name: 'Approved' }))
    fireEvent.change(screen.getByLabelText('Reason (optional)'), { target: { value: 'Budget ok' } })
    fireEvent.click(screen.getByRole('button', { name: 'Confirm on 2 lines' }))

    await waitFor(() =>
      expect(changeMock).toHaveBeenCalledWith({ line_ids: [1, 2], to_status: 'approved', reason: 'Budget ok' }),
    )
    await waitFor(() => expect(onChanged).toHaveBeenCalledWith({ updated_count: 2, closed_purchase_request_ids: [] }))
  })

  it('says so and disables the confirmation when the lines share no transition', () => {
    renderDialog([target({ id: 1, transitions: ['approved'] }), target({ id: 2, transitions: ['received'] })])

    expect(screen.getByText('The selected lines have no status change in common.')).toBeInTheDocument()
    expect(screen.queryByRole('radio')).not.toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Confirm on 2 lines' })).toBeDisabled()
  })

  it('shows the RDA and the current status preselected for a single line, confirmable only on a change', () => {
    renderDialog([
      target({ id: 5, status: 'pending_approval', transitions: ['approved', 'rejected'], capabilities: ['approve'] }),
    ])

    expect(screen.getByText('Change line status')).toBeInTheDocument()
    expect(screen.getByText('RDA #5 - Laptops')).toBeInTheDocument()
    expect(screen.getByText('Function manager')).toBeInTheDocument()
    expect(screen.queryByText('The reason will be applied to all 1 selected lines.')).not.toBeInTheDocument()

    const current = screen.getByRole('radio', { name: 'To approve (current)' })
    expect(current).toBeChecked()
    expect(screen.getByRole('button', { name: 'Confirm' })).toBeDisabled()

    fireEvent.click(screen.getByRole('radio', { name: 'Rejected' }))
    expect(screen.getByRole('button', { name: 'Confirm' })).toBeEnabled()
  })

  it('stays closed with no selected line', () => {
    renderDialog([])

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
  })
})
