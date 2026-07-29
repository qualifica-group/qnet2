import { act, fireEvent, render, screen } from '@testing-library/react'
import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import i18n from '@/i18n'
import { CommissionConfigurationsTable } from './commission-configurations-table'
import { deleteCommissionConfiguration } from './api'

const tableProps = vi.hoisted(() => ({ current: null as null | Record<string, unknown> }))
const activityProps = vi.hoisted(() => ({ current: null as null | Record<string, unknown> }))
const opener = vi.hoisted(() => ({
  openCreate: vi.fn(),
  openView: vi.fn(),
  openEdit: vi.fn(),
}))
const toastError = vi.hoisted(() => vi.fn())

vi.mock('./api', () => ({ deleteCommissionConfiguration: vi.fn() }))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: toastError } }))
vi.mock('@/features/auth/can', () => ({ Can: ({ children }: { children: React.ReactNode }) => children }))
vi.mock('@/components/page-header', () => ({ PageHeader: ({ actions }: { actions: React.ReactNode }) => <div>{actions}</div> }))
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ ...opener, sheet: <div>sheet</div> }),
}))
vi.mock('@/features/table/table-view', async () => {
  const React = await import('react')
  return {
    TableView: React.forwardRef((props: Record<string, unknown>, ref) => {
      tableProps.current = props
      React.useImperativeHandle(ref, () => ({ refresh: vi.fn() }))
      return <div>table</div>
    }),
  }
})
vi.mock('@/features/activity-log/resource-activity-dialog', () => ({
  ResourceActivityDialog: (props: Record<string, unknown>) => {
    activityProps.current = props
    return <div>activity</div>
  },
}))

const row = { id: 7, name: 'Rule' }

describe('CommissionConfigurationsTable', () => {
  beforeAll(async () => {
    await i18n.changeLanguage('en')
  })

  beforeEach(() => {
    vi.clearAllMocks()
    tableProps.current = null
    activityProps.current = null
  })

  it('opens create/view/edit/activity actions', () => {
    render(<CommissionConfigurationsTable />)
    fireEvent.click(screen.getByRole('button', { name: 'New configuration' }))
    expect(opener.openCreate).toHaveBeenCalled()
    const onAction = tableProps.current?.onAction as (action: { key: string }, selectedRow: { id: number; name: string }) => void
    act(() => {
      onAction({ key: 'view' }, row)
      onAction({ key: 'edit' }, row)
      onAction({ key: 'activity' }, row)
      onAction({ key: 'unknown' }, row)
    })
    expect(opener.openView).toHaveBeenCalledWith(row)
    expect(opener.openEdit).toHaveBeenCalledWith(row)
    expect(activityProps.current?.row).toEqual(row)
    act(() => (activityProps.current?.onOpenChange as (open: boolean) => void)(false))
    expect(activityProps.current?.row).toBeNull()
  })

  it('deletes and reports conflict and generic failures', async () => {
    render(<CommissionConfigurationsTable />)
    const onAction = tableProps.current?.onAction as (action: { key: string }, selectedRow: { id: number; name: string }) => void
    vi.mocked(deleteCommissionConfiguration).mockResolvedValueOnce()
    await act(async () => onAction({ key: 'delete' }, row))
    expect(deleteCommissionConfiguration).toHaveBeenCalledWith(7)

    vi.mocked(deleteCommissionConfiguration).mockRejectedValueOnce({
      isAxiosError: true,
      response: { status: 409, data: { message: 'Referenced rule' } },
    })
    await act(async () => onAction({ key: 'delete' }, row))
    expect(toastError).toHaveBeenCalledWith('Referenced rule')

    vi.mocked(deleteCommissionConfiguration).mockRejectedValueOnce(new Error('network'))
    await act(async () => onAction({ key: 'delete' }, row))
    expect(toastError).toHaveBeenCalledWith('Unable to delete the commission configuration.')
  })
})
