import { beforeAll, describe, expect, it, vi } from 'vitest'
import { createRef } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { AbsenceRequestsGrid } from '@/features/absences/requests/absence-requests-grid'
import type { RowActionHandler } from '@/features/table/row-actions'

const mounts = vi.fn()
vi.mock('@/features/table/table-view', () => ({
  TableView: function TableViewStub({ domain, onAction }: { domain: string; onAction: RowActionHandler }) {
    mounts(domain)
    return (
      <button
        type="button"
        onClick={() => onAction({ key: 'view', label: 'view', icon: 'eye', type: 'action', confirm: false }, { id: 12, actions: ['view'] })}
      >
        row-12
      </button>
    )
  },
}))

const label = (key: string) => i18n.t(key)

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

describe('AbsenceRequestsGrid', () => {
  it('shows the three scope tabs, "Mine" first, and switches the grid domain on every tab', () => {
    mounts.mockClear()
    render(<AbsenceRequestsGrid tableRef={createRef()} onOpen={vi.fn()} />)

    const mine = screen.getByRole('tab', { name: label('absences.page.tabs.mine') })
    const toApprove = screen.getByRole('tab', { name: label('absences.page.tabs.to_approve') })
    const all = screen.getByRole('tab', { name: label('absences.page.tabs.all') })
    expect(mine).toHaveAttribute('aria-selected', 'true')
    expect(mounts).toHaveBeenLastCalledWith('absence-requests')

    fireEvent.click(toApprove)
    expect(toApprove).toHaveAttribute('aria-selected', 'true')
    expect(mine).toHaveAttribute('aria-selected', 'false')
    expect(mounts).toHaveBeenLastCalledWith('absence-requests-to-approve')
    fireEvent.click(all)
    expect(all).toHaveAttribute('aria-selected', 'true')
    expect(mounts).toHaveBeenLastCalledWith('absence-requests-all')
  })

  it('opens the detail of the clicked row', () => {
    const onOpen = vi.fn()
    render(<AbsenceRequestsGrid tableRef={createRef()} onOpen={onOpen} />)

    fireEvent.click(screen.getByRole('button', { name: 'row-12' }))
    expect(onOpen).toHaveBeenCalledWith(12)
  })
})
