import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { TaskBoardTemplateReloadDialog } from '@/features/work-orders/task-board/task-board-template-reload-dialog'

const reloadMock = vi.fn()
const confirmName = () => i18n.t('workOrders.taskBoard.templateReload.confirm')

vi.mock('@/features/work-orders/task-board/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/task-board/api')>(
    '@/features/work-orders/task-board/api',
  )
  return { ...actual, reloadWorkOrderTaskTemplate: (...args: unknown[]) => reloadMock(...args) }
})

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: ({ onChange, labels }: { onChange: (value: number | null) => void; labels: { triggerLabel: string } }) => (
    <button type="button" onClick={() => onChange(7)}>
      {`Pick ${labels.triggerLabel}`}
    </button>
  ),
}))

vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }))

function renderDialog(onOpenChange = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <TaskBoardTemplateReloadDialog workOrderId={9} open onOpenChange={onOpenChange} />
    </QueryClientProvider>,
  )
  return onOpenChange
}

function conflict(count: number): AxiosError {
  const headers = new AxiosHeaders()
  return new AxiosError('Conflict', '409', { headers }, null, {
    status: 409,
    statusText: 'Conflict',
    headers: {},
    config: { headers },
    data: { success: false, message: 'Blocked', data: { completed_tasks_count: count } },
  })
}

beforeEach(() => {
  reloadMock.mockReset()
  vi.mocked(toast.error).mockReset()
  vi.mocked(toast.success).mockReset()
})

describe('TaskBoardTemplateReloadDialog (spec 0399)', () => {
  it('keeps the confirm disabled until a template is picked', () => {
    renderDialog()

    expect(screen.getByRole('button', { name: confirmName() })).toBeDisabled()
  })

  it('reloads the picked template and closes', async () => {
    reloadMock.mockResolvedValue(undefined)
    const onOpenChange = renderDialog()

    fireEvent.click(screen.getByRole('button', { name: /^Pick/ }))
    fireEvent.click(screen.getByRole('button', { name: confirmName() }))

    await waitFor(() => expect(onOpenChange).toHaveBeenCalledWith(false))
    expect(reloadMock).toHaveBeenCalledWith(9, 7)
    expect(toast.success).toHaveBeenCalled()
  })

  it('names the completed tasks on a 409 and stays open', async () => {
    reloadMock.mockRejectedValue(conflict(2))
    const onOpenChange = renderDialog()

    fireEvent.click(screen.getByRole('button', { name: /^Pick/ }))
    fireEvent.click(screen.getByRole('button', { name: confirmName() }))

    await waitFor(() =>
      expect(toast.error).toHaveBeenCalledWith(i18n.t('workOrders.taskBoard.templateReload.blocked', { count: 2 })),
    )
    expect(onOpenChange).not.toHaveBeenCalled()
  })
})
