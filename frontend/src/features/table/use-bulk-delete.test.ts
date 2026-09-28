// @vitest-environment jsdom
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, waitFor } from '@testing-library/react'
import type { GridApi } from 'ag-grid-community'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { useBulkDelete } from '@/features/table/use-bulk-delete'
import type { TableRow } from '@/features/table/types'

const confirmMock = vi.fn()
const bulkDeleteTableRowsMock = vi.fn()

vi.mock('@/components/confirm-dialog-context', () => ({
  useConfirm: () => confirmMock,
}))

vi.mock('@/features/table/api', () => ({
  bulkDeleteTableRows: (...args: unknown[]) => bulkDeleteTableRowsMock(...args),
}))

vi.mock('sonner', () => ({
  toast: { success: vi.fn(), warning: vi.fn(), error: vi.fn() },
}))

function gridApiStub() {
  return { deselectAll: vi.fn() } as unknown as GridApi<TableRow>
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  confirmMock.mockReset()
  bulkDeleteTableRowsMock.mockReset()
  vi.mocked(toast.success).mockClear()
  vi.mocked(toast.warning).mockClear()
  vi.mocked(toast.error).mockClear()
})

describe('useBulkDelete', () => {
  it('does nothing for an empty selection', async () => {
    const refresh = vi.fn()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi: gridApiStub(), refresh }),
    )

    const performed = await result.current.runBulkDelete([])

    expect(performed).toBe(false)
    expect(confirmMock).not.toHaveBeenCalled()
    expect(bulkDeleteTableRowsMock).not.toHaveBeenCalled()
  })

  it('does not call the API when the user cancels the confirm', async () => {
    confirmMock.mockResolvedValue(false)
    const refresh = vi.fn()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi: gridApiStub(), refresh }),
    )

    const performed = await result.current.runBulkDelete([1, 2])

    expect(performed).toBe(false)
    expect(bulkDeleteTableRowsMock).not.toHaveBeenCalled()
    expect(refresh).not.toHaveBeenCalled()
  })

  it('deletes, toasts success, clears the grid selection and refreshes', async () => {
    confirmMock.mockResolvedValue(true)
    bulkDeleteTableRowsMock.mockResolvedValue({ deleted: 2, failed: [] })
    const refresh = vi.fn()
    const gridApi = gridApiStub()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi, refresh }),
    )

    const performed = await result.current.runBulkDelete([1, 2])

    expect(performed).toBe(true)
    expect(bulkDeleteTableRowsMock).toHaveBeenCalledWith('users', [1, 2])
    expect(toast.success).toHaveBeenCalledWith('2 rows deleted.')
    expect(toast.warning).not.toHaveBeenCalled()
    expect(gridApi.deselectAll).toHaveBeenCalledTimes(1)
    expect(refresh).toHaveBeenCalledTimes(1)
  })

  it('toasts a partial-failure warning when some rows were skipped', async () => {
    confirmMock.mockResolvedValue(true)
    bulkDeleteTableRowsMock.mockResolvedValue({
      deleted: 1,
      failed: [{ id: 2, reason: 'forbidden' }],
    })
    const refresh = vi.fn()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi: gridApiStub(), refresh }),
    )

    const performed = await result.current.runBulkDelete([1, 2])

    expect(performed).toBe(true)
    expect(toast.warning).toHaveBeenCalledWith(
      '1 rows deleted, 1 could not be deleted.',
    )
    expect(toast.success).not.toHaveBeenCalled()
  })

  it('toasts a generic error and keeps the selection when the request fails', async () => {
    confirmMock.mockResolvedValue(true)
    bulkDeleteTableRowsMock.mockRejectedValue(new Error('network'))
    const refresh = vi.fn()
    const gridApi = gridApiStub()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi, refresh }),
    )

    const performed = await result.current.runBulkDelete([1])

    expect(performed).toBe(false)
    expect(toast.error).toHaveBeenCalledWith(
      'Unable to delete the selected rows. Please try again.',
    )
    expect(gridApi.deselectAll).not.toHaveBeenCalled()
    expect(refresh).not.toHaveBeenCalled()
  })

  it('exposes isDeleting while the request is in flight', async () => {
    confirmMock.mockResolvedValue(true)
    let resolveDelete: (value: { deleted: number; failed: never[] }) => void = () => {}
    bulkDeleteTableRowsMock.mockReturnValue(
      new Promise((resolve) => {
        resolveDelete = resolve
      }),
    )
    const refresh = vi.fn()
    const { result } = renderHook(() =>
      useBulkDelete({ domain: 'users', gridApi: gridApiStub(), refresh }),
    )

    const pending = result.current.runBulkDelete([1])
    await waitFor(() => expect(result.current.isDeleting).toBe(true))

    resolveDelete({ deleted: 1, failed: [] })
    await pending

    await waitFor(() => expect(result.current.isDeleting).toBe(false))
  })
})
