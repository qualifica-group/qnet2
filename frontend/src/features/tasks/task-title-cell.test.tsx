import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import i18n from '@/i18n'
import { TaskCompleteRowContext } from '@/features/tasks/task-complete-row-context'
import { TaskTitleCell } from '@/features/tasks/task-title-cell'
import type { TableRow } from '@/features/table/types'

/** User directive 2026-10-06: the complete toggle before the grid's task title. */

beforeAll(async () => {
  await i18n.changeLanguage('it')
})

function row(group: string, actions: string[]): TableRow {
  return { id: 7, actions, title: 'Alfa', task_status: { id: 1, name: 'S', color: null, icon: null, group } }
}

function renderCell(data: TableRow, completeRow: ((row: TableRow) => void) | null = vi.fn()) {
  const params = { value: data.title, data } as unknown as ICellRendererParams<TableRow>
  render(
    <TaskCompleteRowContext.Provider value={completeRow}>
      <TaskTitleCell {...params} />
    </TaskCompleteRowContext.Provider>,
  )
  return completeRow
}

describe('TaskTitleCell', () => {
  it('opens the complete flow for a completable row, and the grid never sees the click', () => {
    const data = row('open', ['view', 'complete'])
    const completeRow = renderCell(data)
    const gridListener = vi.fn()
    document.addEventListener('click', gridListener)

    fireEvent.click(screen.getByRole('button', { name: i18n.t('tasks.actions.complete.label') }))

    expect(completeRow).toHaveBeenCalledWith(data)
    expect(gridListener).not.toHaveBeenCalled()
    document.removeEventListener('click', gridListener)
    expect(screen.getByText('Alfa')).toBeInTheDocument()
  })

  it('shows the filled green icon on a completed task, with no action', () => {
    renderCell(row('closed_positive', ['view']))

    expect(screen.getByRole('img', { name: i18n.t('tasks.actions.complete.done') })).toHaveClass('fill-success')
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('offers no toggle when the row may not be completed', () => {
    renderCell(row('open', ['view']))

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('offers no toggle outside the Task grid (no provider)', () => {
    renderCell(row('open', ['complete']), null)

    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })
})
