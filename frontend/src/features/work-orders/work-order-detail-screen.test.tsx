import { describe, expect, it, vi } from 'vitest'
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useState } from 'react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { fetchWorkOrder, workOrderDetailQueryKey } from '@/features/work-orders/api'
import { WorkOrderDetailScreen } from '@/features/work-orders/work-order-screens'
import type { WorkOrderDetailWithPermissions } from '@/features/work-orders/types'

vi.mock('@/features/work-orders/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-orders/api')>()),
  fetchWorkOrder: vi.fn(),
}))

/** Stands in for the detail: its own state plays the active tab, lost only on a remount. */
vi.mock('@/features/work-orders/work-order-detail', () => ({
  WorkOrderDetailView: ({ workOrder, onDeleted }: { workOrder: WorkOrderDetailWithPermissions; onDeleted?: () => void }) => {
    const [tab, setTab] = useState('info')
    return (
      <div>
        <span>title:{workOrder.title}</span>
        <span>tab:{tab}</span>
        <button type="button" onClick={() => setTab('tasks')}>
          open-tasks
        </button>
        <button type="button" onClick={onDeleted}>
          deleted
        </button>
      </div>
    )
  },
}))

vi.mock('@/features/modules/use-form-leave-guard', () => ({ useFormLeaveGuard: () => ({}) }))

const WORK_ORDER_ID = 4

function workOrder(title: string) {
  return { id: WORK_ORDER_ID, title } as WorkOrderDetailWithPermissions
}

describe('WorkOrderDetailScreen', () => {
  it('keeps the open tab when a save inside it refreshes the commessa', async () => {
    let resolveRefetch!: (value: WorkOrderDetailWithPermissions) => void
    const refetched = new Promise<WorkOrderDetailWithPermissions>((resolve) => {
      resolveRefetch = resolve
    })
    vi.mocked(fetchWorkOrder).mockResolvedValueOnce(workOrder('Prima')).mockReturnValueOnce(refetched)
    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    render(
      <QueryClientProvider client={client}>
        <MemoryRouter>
          <WorkOrderDetailScreen id={WORK_ORDER_ID} onChanged={vi.fn()} onEdit={vi.fn()} />
        </MemoryRouter>
      </QueryClientProvider>,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'open-tasks' }))
    expect(screen.getByText('tab:tasks')).toBeInTheDocument()

    // The refetch stays in flight long enough for a skeleton to be committed, if there were one.
    act(() => {
      void client.invalidateQueries({ queryKey: workOrderDetailQueryKey(WORK_ORDER_ID) })
    })
    await waitFor(() => expect(client.isFetching()).toBe(1))
    expect(screen.getByText('tab:tasks')).toBeInTheDocument()

    await act(async () => resolveRefetch(workOrder('Dopo')))
    await waitFor(() => expect(screen.getByText('title:Dopo')).toBeInTheDocument())
    expect(screen.getByText('tab:tasks')).toBeInTheDocument()
  })

  it('after a delete closes the Sheet (modal host) and refreshes its grid', async () => {
    vi.mocked(fetchWorkOrder).mockResolvedValueOnce(workOrder('Da eliminare'))
    const onChanged = vi.fn()
    const onSaved = vi.fn()
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter>
          <WorkOrderDetailScreen id={WORK_ORDER_ID} onChanged={onChanged} onSaved={onSaved} />
        </MemoryRouter>
      </QueryClientProvider>,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'deleted' }))

    expect(onChanged).toHaveBeenCalledOnce()
    expect(onSaved).toHaveBeenCalledOnce()
  })

  it('after a delete on the record page goes back to the Commesse list', async () => {
    vi.mocked(fetchWorkOrder).mockResolvedValueOnce(workOrder('Da eliminare'))
    render(
      <QueryClientProvider client={new QueryClient({ defaultOptions: { queries: { retry: false } } })}>
        <MemoryRouter initialEntries={[`/work-orders/${WORK_ORDER_ID}`]}>
          <Routes>
            <Route path="/work-orders/:id" element={<WorkOrderDetailScreen id={WORK_ORDER_ID} />} />
            <Route path="/work-orders" element={<span>commesse-list</span>} />
          </Routes>
        </MemoryRouter>
      </QueryClientProvider>,
    )

    fireEvent.click(await screen.findByRole('button', { name: 'deleted' }))

    expect(await screen.findByText('commesse-list')).toBeInTheDocument()
  })
})
