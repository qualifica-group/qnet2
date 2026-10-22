import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { createRef } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { toast } from 'sonner'
import i18n from '@/i18n'
import { AbsenceRequestsGrid } from '@/features/absences/requests/absence-requests-grid'
import { absenceWrapper } from '@/features/absences/requests/absence-request-fixtures'
import type { AbsenceScope } from '@/features/absences/types'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableRow } from '@/features/table/types'
import type { BulkAction, TableSelection } from '@/features/table/use-bulk-actions-slot'

interface StubProps {
  domain: string
  onAction: RowActionHandler
  isRowSelectable?: (row: TableRow) => boolean
  getBulkActions?: (selection: TableSelection) => BulkAction[]
  disableBuiltinDelete?: boolean
}

const mounts = vi.fn()
const stubProps = vi.fn()
vi.mock('@/features/table/table-view', () => ({
  TableView: function TableViewStub(props: StubProps) {
    mounts(props.domain)
    stubProps(props)
    const bulk = props.getBulkActions?.({ ids: [1, 2], rows: [] }) ?? []
    const action = (key: string) => ({ key, label: key, icon: 'eye', type: 'action' as const, confirm: false })
    return (
      <div>
        {['view', 'approve', 'reject'].map((key) => (
          <button key={key} type="button" onClick={() => props.onAction(action(key), { id: 12, actions: [key] })}>
            row-{key}
          </button>
        ))}
        {bulk.map((item) => (
          <button key={item.key} type="button" onClick={item.onSelect}>
            bulk-{item.key}
          </button>
        ))}
      </div>
    )
  },
}))

const approveMock = vi.fn()
const rejectMock = vi.fn()
const bulkMock = vi.fn()
vi.mock('@/features/absences/api', () => ({
  approveAbsenceRequest: (id: number) => approveMock(id),
  rejectAbsenceRequest: (id: number, payload: unknown) => rejectMock(id, payload),
  bulkDecideAbsenceRequests: (payload: unknown) => bulkMock(payload),
}))
vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn(), warning: vi.fn() } }))

const label = (key: string, options?: Record<string, unknown>) => i18n.t(key, options)

function renderGrid(scope: AbsenceScope = 'mine', props: { onOpen?: (id: number) => void; onScopeChange?: (scope: AbsenceScope) => void; toApproveCount?: number } = {}) {
  return render(
    <AbsenceRequestsGrid
      tableRef={createRef()}
      onOpen={props.onOpen ?? vi.fn()}
      scope={scope}
      onScopeChange={props.onScopeChange ?? vi.fn()}
      toApproveCount={props.toApproveCount ?? 0}
    />,
    { wrapper: absenceWrapper() },
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

beforeEach(() => {
  vi.clearAllMocks()
})

describe('AbsenceRequestsGrid', () => {
  it('shows the three scope tabs, the active one selected, and mounts the domain of the scope', () => {
    renderGrid('to_approve')

    expect(screen.getByRole('tab', { name: new RegExp(`^${label('absences.page.tabs.to_approve')}`) })).toHaveAttribute('aria-selected', 'true')
    expect(screen.getByRole('tab', { name: label('absences.page.tabs.mine') })).toHaveAttribute('aria-selected', 'false')
    expect(mounts).toHaveBeenLastCalledWith('absence-requests-to-approve')
  })

  it('reports the clicked tab', () => {
    const onScopeChange = vi.fn()
    renderGrid('mine', { onScopeChange })

    fireEvent.click(screen.getByRole('tab', { name: label('absences.page.tabs.all') }))
    expect(onScopeChange).toHaveBeenCalledWith('all')
  })

  it('shows the counter on the To approve tab only when positive', () => {
    const { unmount } = renderGrid('mine', { toApproveCount: 3 })
    expect(screen.getByRole('tab', { name: new RegExp(`${label('absences.page.tabs.to_approve')}\\s*3`) })).toBeInTheDocument()
    unmount()

    renderGrid('mine', { toApproveCount: 0 })
    expect(screen.getByRole('tab', { name: label('absences.page.tabs.to_approve') })).toBeInTheDocument()
  })

  it('opens the detail of the clicked row', () => {
    const onOpen = vi.fn()
    renderGrid('mine', { onOpen })

    fireEvent.click(screen.getByRole('button', { name: 'row-view' }))
    expect(onOpen).toHaveBeenCalledWith(12)
  })

  it('offers the multiple selection only on the To approve tab, without the built-in delete', () => {
    renderGrid('mine')
    expect(stubProps).toHaveBeenLastCalledWith(expect.objectContaining({ getBulkActions: undefined, isRowSelectable: undefined }))

    renderGrid('to_approve')
    const props = stubProps.mock.lastCall?.[0] as StubProps
    expect(props.disableBuiltinDelete).toBe(true)
    expect(props.isRowSelectable?.({ id: 1, actions: ['view', 'approve'] })).toBe(true)
    expect(props.isRowSelectable?.({ id: 2, actions: ['view'] })).toBe(false)
  })
})

describe('AbsenceRequestsGrid quick decisions (spec 0304 AC-021)', () => {
  it('approves the row through the API and confirms with a toast', async () => {
    approveMock.mockResolvedValue({})
    renderGrid('to_approve')

    fireEvent.click(screen.getByRole('button', { name: 'row-approve' }))

    await waitFor(() => expect(approveMock).toHaveBeenCalledWith(12))
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('absences.requests.actions.approved')))
  })

  it('rejects the row with the reason typed in the dialog', async () => {
    rejectMock.mockResolvedValue({})
    renderGrid('to_approve')

    fireEvent.click(screen.getByRole('button', { name: 'row-reject' }))
    const dialog = await screen.findByRole('dialog', { name: label('absences.requests.reject.title') })
    fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Periodo di chiusura' } })
    fireEvent.click(within(dialog).getByRole('button', { name: label('absences.requests.reject.confirm') }))

    await waitFor(() => expect(rejectMock).toHaveBeenCalledWith(12, { rejection_reason: 'Periodo di chiusura' }))
  })

  it('approves the selection after the confirmation and reports a mixed outcome', async () => {
    bulkMock.mockResolvedValue({ decided: [1], failed: [{ id: 2, message: 'Non autorizzato' }] })
    renderGrid('to_approve')

    fireEvent.click(screen.getByRole('button', { name: 'bulk-approve' }))
    const confirm = await screen.findByRole('alertdialog')
    fireEvent.click(within(confirm).getByRole('button', { name: label('absences.bulk.approveConfirm') }))

    await waitFor(() => expect(bulkMock).toHaveBeenCalledWith({ action: 'approve', ids: [1, 2] }))
    await waitFor(() =>
      expect(toast.warning).toHaveBeenCalledWith(
        label('absences.bulk.result', { decided: 1, failed: 1 }),
        { description: label('absences.bulk.failedItem', { id: 2, message: 'Non autorizzato' }) },
      ),
    )
  })

  it('does not call the API when the multiple approval is not confirmed', async () => {
    renderGrid('to_approve')

    fireEvent.click(screen.getByRole('button', { name: 'bulk-approve' }))
    const confirm = await screen.findByRole('alertdialog')
    fireEvent.click(within(confirm).getByRole('button', { name: /annulla|cancel/i }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(bulkMock).not.toHaveBeenCalled()
  })

  it('rejects the selection with one shared reason', async () => {
    bulkMock.mockResolvedValue({ decided: [1, 2], failed: [] })
    renderGrid('to_approve')

    fireEvent.click(screen.getByRole('button', { name: 'bulk-reject' }))
    const dialog = await screen.findByRole('dialog', { name: label('absences.bulk.rejectTitle', { count: 2 }) })
    fireEvent.change(within(dialog).getByRole('textbox'), { target: { value: 'Chiusura estiva' } })
    fireEvent.click(within(dialog).getByRole('button', { name: label('absences.requests.reject.confirm') }))

    await waitFor(() =>
      expect(bulkMock).toHaveBeenCalledWith({ action: 'reject', ids: [1, 2], rejection_reason: 'Chiusura estiva' }),
    )
    await waitFor(() => expect(toast.success).toHaveBeenCalledWith(label('absences.bulk.allDecided', { decided: 2 })))
  })
})
