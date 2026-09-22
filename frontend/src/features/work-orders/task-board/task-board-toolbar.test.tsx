// Requirement changed (user directive 2026-09-22): the inline segment/chip
// filters were replaced by the Gestione Richieste idiom — applied-filter chips
// in a contained bar plus a "Modifica filtri" sheet that applies a draft.
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, within } from '@testing-library/react'
import '@/i18n'
import { TaskBoardToolbar } from '@/features/work-orders/task-board/task-board-toolbar'
import { DEFAULT_TASK_BOARD_FILTERS } from '@/features/work-orders/task-board/task-board-filters'
import type { TaskBoardFilterOptions } from '@/features/work-orders/task-board/task-board-filters'

const OPTIONS: TaskBoardFilterOptions = {
  requesters: [{ id: 21, name: 'Bruno Bianchi' }],
  assignees: [{ id: 31, name: 'Dario Dini' }],
  watchers: [{ id: 41, name: 'Elena Verdi' }],
  taskTypes: [{ id: 5, name: 'Sopralluogo', color: 'blue', icon: null }],
  taskPriorities: [{ id: 9, name: 'Alta', color: 'red', icon: null }],
  taskStatuses: [{ id: 3, name: 'In lavorazione', color: 'blue', icon: null }],
  taskImportances: [{ id: 7, name: 'Critica', color: 'red', icon: null }],
}

function renderToolbar(overrides: Partial<Parameters<typeof TaskBoardToolbar>[0]> = {}) {
  const onFiltersChange = vi.fn()
  const onViewModeChange = vi.fn()
  render(
    <TaskBoardToolbar
      filters={DEFAULT_TASK_BOARD_FILTERS}
      onFiltersChange={onFiltersChange}
      options={OPTIONS}
      viewMode="list"
      onViewModeChange={onViewModeChange}
      {...overrides}
    />,
  )
  return { onFiltersChange, onViewModeChange }
}

async function openSheet() {
  fireEvent.click(screen.getByRole('button', { name: 'Modifica filtri' }))
  return screen.findByRole('dialog', { name: 'Filtri dei task' })
}

describe('TaskBoardToolbar — applied filters bar', () => {
  it('shows Stato and Scadenza chips by default (AC-025: Stato "Aperti")', () => {
    renderToolbar()

    const chips = screen.getByRole('list', { name: 'Filtri applicati' })
    expect(within(chips).getByText('Aperti')).toBeInTheDocument()
    expect(within(chips).getByText('Tutte')).toBeInTheDocument()
    expect(within(chips).queryByText('Tipo')).not.toBeInTheDocument()
  })

  it('adds a chip naming the picked values of an id filter', () => {
    renderToolbar({
      filters: {
        ...DEFAULT_TASK_BOARD_FILTERS,
        taskTypeIds: [5],
        taskStatusIds: [3],
        taskImportanceIds: [7],
        assignment: 'assigned_to_me',
      },
    })

    const chips = screen.getByRole('list', { name: 'Filtri applicati' })
    expect(within(chips).getByText('Sopralluogo')).toBeInTheDocument()
    expect(within(chips).getByText('In lavorazione')).toBeInTheDocument()
    expect(within(chips).getByText('Critica')).toBeInTheDocument()
    expect(within(chips).getByText('Assegnati a me')).toBeInTheDocument()
  })

  it('emits the next filters object on a search keystroke', () => {
    const { onFiltersChange } = renderToolbar()

    fireEvent.change(screen.getByRole('searchbox', { name: 'Cerca per titolo…' }), { target: { value: 'x' } })

    expect(onFiltersChange).toHaveBeenCalledWith({ ...DEFAULT_TASK_BOARD_FILTERS, search: 'x' })
  })

  it('shows "Azzera filtri" only off the default, and resets to DEFAULT', () => {
    const { onFiltersChange } = renderToolbar({ filters: { ...DEFAULT_TASK_BOARD_FILTERS, due: 'today' } })

    fireEvent.click(screen.getByRole('button', { name: 'Azzera filtri' }))

    expect(onFiltersChange).toHaveBeenCalledWith(DEFAULT_TASK_BOARD_FILTERS)
  })

  it('hides "Azzera filtri" on the default filters', () => {
    renderToolbar()

    expect(screen.queryByRole('button', { name: 'Azzera filtri' })).not.toBeInTheDocument()
  })

  it('toggles Lista/Board and reports the pressed state', () => {
    const { onViewModeChange } = renderToolbar()

    expect(screen.getByRole('button', { name: 'Lista' })).toHaveAttribute('aria-pressed', 'true')
    fireEvent.click(screen.getByRole('button', { name: 'Board' }))

    expect(onViewModeChange).toHaveBeenCalledWith('kanban')
  })
})

describe('TaskBoardToolbar — "Modifica filtri" sheet', () => {
  it('applies the edited draft only on "Applica"', async () => {
    const { onFiltersChange } = renderToolbar()
    const sheet = await openSheet()

    fireEvent.click(within(sheet).getByRole('radio', { name: 'Completati' }))
    fireEvent.click(within(sheet).getByRole('radio', { name: 'Scadute' }))
    fireEvent.click(within(sheet).getByRole('radio', { name: 'Richiesti da me' }))
    expect(onFiltersChange).not.toHaveBeenCalled()

    fireEvent.click(within(sheet).getByRole('button', { name: 'Applica' }))

    expect(onFiltersChange).toHaveBeenCalledWith({
      ...DEFAULT_TASK_BOARD_FILTERS,
      status: 'completed',
      due: 'overdue',
      assignment: 'requested_by_me',
    })
  })

  it('picks ids through the multi-select', async () => {
    const { onFiltersChange } = renderToolbar()
    const sheet = await openSheet()

    fireEvent.click(within(sheet).getByRole('button', { name: 'Tipo' }))
    fireEvent.click(await screen.findByRole('checkbox', { name: 'Sopralluogo' }))
    fireEvent.click(within(sheet).getByRole('button', { name: 'Applica' }))

    expect(onFiltersChange).toHaveBeenCalledWith({ ...DEFAULT_TASK_BOARD_FILTERS, taskTypeIds: [5] })
  })

  it('picks a status and an importance level', async () => {
    const { onFiltersChange } = renderToolbar()
    const sheet = await openSheet()

    fireEvent.click(within(sheet).getByRole('button', { name: 'Stato' }))
    fireEvent.click(await screen.findByRole('checkbox', { name: 'In lavorazione' }))
    fireEvent.click(within(sheet).getByRole('button', { name: 'Importanza' }))
    fireEvent.click(await screen.findByRole('checkbox', { name: 'Critica' }))
    fireEvent.click(within(sheet).getByRole('button', { name: 'Applica' }))

    expect(onFiltersChange).toHaveBeenCalledWith({ ...DEFAULT_TASK_BOARD_FILTERS, taskStatusIds: [3], taskImportanceIds: [7] })
  })

  it('discards the draft on "Annulla"', async () => {
    const { onFiltersChange } = renderToolbar()
    const sheet = await openSheet()

    const statusGroup = within(sheet).getByRole('radiogroup', { name: 'Mostra' })
    fireEvent.click(within(statusGroup).getByRole('radio', { name: 'Tutti' }))
    fireEvent.click(within(sheet).getByRole('button', { name: 'Annulla' }))

    expect(onFiltersChange).not.toHaveBeenCalled()
  })

  it('resets the draft to the defaults but keeps the search', async () => {
    const { onFiltersChange } = renderToolbar({
      filters: { ...DEFAULT_TASK_BOARD_FILTERS, search: 'impianto', status: 'blocked', watcherIds: [41] },
    })
    const sheet = await openSheet()

    fireEvent.click(within(sheet).getByRole('button', { name: 'Azzera filtri' }))
    fireEvent.click(within(sheet).getByRole('button', { name: 'Applica' }))

    expect(onFiltersChange).toHaveBeenCalledWith({ ...DEFAULT_TASK_BOARD_FILTERS, search: 'impianto' })
  })
})
