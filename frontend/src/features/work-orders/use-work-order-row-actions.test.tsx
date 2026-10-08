import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { act, fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { fetchWorkOrder, updateWorkOrder } from '@/features/work-orders/api'
import { useWorkOrderRowActions } from '@/features/work-orders/use-work-order-row-actions'
import type { TableActionDefinition } from '@/features/table/types'

/**
 * The grid's closure row actions (user directive 2026-10-06: "stessa action
 * sulla tabella commesse"): `force_close` opens the reason dialog — the row
 * does not carry the open tasks, the dialog reads them off the record —
 * and `reopen` (already confirmed by the grid, `confirm: true`) PATCHes back.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

vi.mock('@/features/work-orders/api', async () => {
  const actual = await vi.importActual<typeof import('@/features/work-orders/api')>('@/features/work-orders/api')
  return { ...actual, updateWorkOrder: vi.fn(), fetchWorkOrder: vi.fn(), deleteWorkOrder: vi.fn() }
})

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)
const ROW = { id: 7, actions: ['force_close', 'reopen'] }

function action(key: string): TableActionDefinition {
  return { key, label: key, icon: 'lock', type: 'action', confirm: false, permission: 'work-orders.update' } as TableActionDefinition
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <MemoryRouter>
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    </MemoryRouter>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  vi.mocked(updateWorkOrder).mockReset()
  vi.mocked(fetchWorkOrder).mockReset()
})

describe('useWorkOrderRowActions — closure', () => {
  it('opens the force-close dialog, warns about the open tasks and PATCHes the reason', async () => {
    vi.mocked(fetchWorkOrder).mockResolvedValue({ id: 7, open_tasks_count: 1 } as never)
    vi.mocked(updateWorkOrder).mockResolvedValue({ id: 7 } as never)
    const onMutated = vi.fn()
    const Wrapper = wrapper()
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated }), { wrapper: Wrapper })

    act(() => result.current.handleAction(action('force_close'), ROW))
    render(<>{result.current.forceCloseDialog}</>, { wrapper: Wrapper })

    expect(await screen.findByRole('alert')).toHaveTextContent('1 open task will be closed with a negative outcome.')
    expect(fetchWorkOrder).toHaveBeenCalledWith(7)

    // The reason is mandatory: nothing is sent without it.
    fireEvent.click(screen.getByRole('button', { name: label('workOrders.actions.forceClose.submit') }))
    expect(await screen.findByText(label('workOrders.form.forceCloseReasonRequired'))).toBeInTheDocument()
    expect(updateWorkOrder).not.toHaveBeenCalled()

    fireEvent.change(screen.getByRole('textbox', { name: label('workOrders.form.forceCloseReason') }), {
      target: { value: 'Annullata dal cliente' },
    })
    fireEvent.click(screen.getByRole('button', { name: label('workOrders.actions.forceClose.submit') }))

    await waitFor(() =>
      expect(updateWorkOrder).toHaveBeenCalledWith(7, { is_force_closed: true, force_close_reason: 'Annullata dal cliente' }),
    )
    await waitFor(() => expect(onMutated).toHaveBeenCalledOnce())
  })

  it('reopens the row and refreshes the grid', async () => {
    vi.mocked(updateWorkOrder).mockResolvedValue({ id: 7 } as never)
    const onMutated = vi.fn()
    const { result } = renderHook(() => useWorkOrderRowActions({ onMutated }), { wrapper: wrapper() })

    act(() => result.current.handleAction(action('reopen'), ROW))

    await waitFor(() => expect(updateWorkOrder).toHaveBeenCalledWith(7, { is_force_closed: false, force_close_reason: null }))
    await waitFor(() => expect(onMutated).toHaveBeenCalledOnce())
  })
})
