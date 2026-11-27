import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { WorkOrderCostsQuoteSyncButton } from '@/features/work-order-costs/work-order-costs-quote-sync-button'
import { OVERVIEW } from '@/features/work-order-costs/work-order-costs-fixtures'
import type { WorkOrderCostOverview } from '@/features/work-order-costs/types'

const syncFromQuoteMock = vi.fn<(id: number) => Promise<WorkOrderCostOverview>>()
vi.mock('@/features/work-order-costs/api', () => ({
  syncWorkOrderCostsFromQuote: (id: number) => syncFromQuoteMock(id),
}))

const toastMock = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }))
vi.mock('sonner', () => ({ toast: toastMock }))

const NO_ACTUAL_COSTS: WorkOrderCostOverview = { ...OVERVIEW, lines: [] }
const NO_BUDGET: WorkOrderCostOverview = { ...OVERVIEW, budget: { allocated_lines: [], unallocated_lines: [] } }

function renderButton(overview: WorkOrderCostOverview) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderCostsQuoteSyncButton workOrderId={4} overview={overview} disabled={false} />
    </QueryClientProvider>,
  )
}

function openConfirm() {
  fireEvent.click(screen.getByRole('button', { name: 'Sync with the offer' }))
  return screen.findByRole('alertdialog')
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  syncFromQuoteMock.mockReset().mockResolvedValue(OVERVIEW)
  toastMock.success.mockReset()
  toastMock.error.mockReset()
})

describe('WorkOrderCostsQuoteSyncButton (spec 0436)', () => {
  it('AC-008: without offer cost lines the popup explains there is nothing to sync, Close only', async () => {
    renderButton(NO_BUDGET)

    expect(await openConfirm()).toHaveTextContent('The offer has no cost lines for this work order.')
    expect(screen.queryByRole('button', { name: 'Sync' })).not.toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Close' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(syncFromQuoteMock).not.toHaveBeenCalled()
  })

  it('AC-008: without actual costs asks to copy the offer lines', async () => {
    renderButton(NO_ACTUAL_COSTS)

    expect(await openConfirm()).toHaveTextContent("The offer's 2 cost lines will be copied into the actual costs, dated today.")
    expect(syncFromQuoteMock).not.toHaveBeenCalled()
  })

  it('AC-008: with actual costs warns they will be replaced, and syncs on confirm', async () => {
    renderButton(OVERVIEW)

    expect(await openConfirm()).toHaveTextContent(
      "The actual cost already entered will be replaced by the offer's cost products (2 lines)",
    )
    fireEvent.click(screen.getByRole('button', { name: 'Sync' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(syncFromQuoteMock).toHaveBeenCalledWith(4)
    expect(toastMock.success).toHaveBeenCalledWith('Actual costs synced with the offer.')
  })

  it('cancel closes the dialog without syncing', async () => {
    renderButton(OVERVIEW)
    await openConfirm()

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(syncFromQuoteMock).not.toHaveBeenCalled()
  })

  it("a 422 keeps the dialog open and shows the server's reason", async () => {
    const reason = 'Cannot sync: these products can no longer be used as a cost: Aula esterna.'
    syncFromQuoteMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { message: reason, errors: { lines: [reason] } },
      } as AxiosResponse),
    )
    renderButton(OVERVIEW)
    await openConfirm()

    fireEvent.click(screen.getByRole('button', { name: 'Sync' }))

    await waitFor(() => expect(toastMock.error).toHaveBeenCalledWith(reason))
    expect(screen.getByRole('alertdialog')).toBeInTheDocument()
  })
})
