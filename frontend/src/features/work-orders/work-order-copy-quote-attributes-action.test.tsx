import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { toast } from 'sonner'
import '@/i18n'
import { WorkOrderCopyQuoteAttributesAction } from '@/features/work-orders/work-order-copy-quote-attributes-action'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

const copyMock = vi.hoisted(() => vi.fn())
vi.mock('@/features/work-orders/api', () => ({
  copyWorkOrderQuoteAttributes: copyMock,
  workOrderDetailQueryKey: (id: number) => ['work-orders', 'detail', id],
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

function workOrder(overrides: { update: boolean; hasOffer: boolean }): WorkOrderDetailWithPermissions {
  return {
    id: 7,
    quote: overrides.hasOffer ? { id: 1 } : null,
    source_quotes: overrides.hasOffer ? [{ id: 1 }] : [],
    permissions: { resource: { update: overrides.update } },
  } as unknown as WorkOrderDetailWithPermissions
}

function renderAction(wo: WorkOrderDetailWithPermissions, onChanged?: () => void) {
  const client = new QueryClient()
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderCopyQuoteAttributesAction workOrder={wo} onChanged={onChanged} />
    </QueryClientProvider>,
  )
}

describe('WorkOrderCopyQuoteAttributesAction', () => {
  it('calls the endpoint and shows the server message as a toast', async () => {
    copyMock.mockResolvedValue({ message: '2 fields copied from the offer', workOrder: workOrder({ update: true, hasOffer: true }) })
    const onChanged = vi.fn()
    renderAction(workOrder({ update: true, hasOffer: true }), onChanged)

    fireEvent.click(screen.getByRole('button'))

    await waitFor(() => expect(toast.success).toHaveBeenCalledWith('2 fields copied from the offer'))
    expect(copyMock).toHaveBeenCalledWith(7)
    expect(onChanged).toHaveBeenCalled()
  })

  it('is hidden without update permission or without an offer', () => {
    const { container, rerender } = renderAction(workOrder({ update: false, hasOffer: true }))
    expect(container).toBeEmptyDOMElement()

    rerender(
      <QueryClientProvider client={new QueryClient()}>
        <WorkOrderCopyQuoteAttributesAction workOrder={workOrder({ update: true, hasOffer: false })} />
      </QueryClientProvider>,
    )
    expect(container).toBeEmptyDOMElement()
  })
})
