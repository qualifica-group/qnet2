import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { TasksTable } from '@/features/tasks/task-table'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * The "notes" row action (spec 0156 D-5, AC-006): clicking it opens the
 * agnostic `NotesDialog` on the row's own thread, under the `tasks`
 * entity_type (`config/notes.php`); the grid refreshes on every write inside
 * it (`onThreadChanged`) and again on close, so the `notes_count` badge on
 * the action stays current without waiting for the dialog to be dismissed —
 * same contract `opportunities-table-notes.test.tsx` already covers for its
 * own domain. The generic `<TableView>` is stubbed (its own behavior, and
 * the count-badge rendering itself, are covered elsewhere: `row-actions.test.tsx`);
 * this suite is only about what the tasks adapter does with the action and
 * the dialog's open state.
 */

vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: () => true, hasRole: () => false, roles: [], isLoading: false }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

// TasksTable's status-cell interceptor resolves the task-statuses catalog on
// mount (`useTaskStatusGroupLookup`); stubbed empty so no real request fires.
vi.mock('@/features/for-select/api', () => ({
  fetchForSelect: vi.fn().mockResolvedValue({ items: [], nextCursor: null }),
}))

const notesSectionMock = vi.fn()
vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; onThreadChanged?: () => void }) => {
    notesSectionMock(props)
    return <div>{`notes-section:${props.entityType}:${props.entityId}`}</div>
  },
}))

const ROW: TableRow = {
  id: 90,
  actions: ['view', 'edit', 'delete', 'notes'],
  notes_count: 3,
}

function action(key: string): TableActionDefinition {
  return {
    key,
    label: `tasks.actions.${key}`,
    icon: key === 'notes' ? 'messages-square' : 'eye',
    type: 'action',
    confirm: false,
    count_field: key === 'notes' ? 'notes_count' : null,
  }
}

const refreshMock = vi.fn()
let capturedOnAction: RowActionHandler | null = null

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void; clearSelection: () => void }, { domain: string; onAction: RowActionHandler }>(
    function TableViewStub({ domain, onAction }, ref) {
      useImperativeHandle(ref, () => ({ refresh: refreshMock, clearSelection: vi.fn() }))
      capturedOnAction = onAction
      return (
        <div role="region" aria-label={`table-${domain}`}>
          <button type="button" onClick={() => onAction(action('notes'), ROW)}>
            trigger-notes
          </button>
        </div>
      )
    },
  ),
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ConfirmDialogProvider>
          <TasksTable />
        </ConfirmDialogProvider>
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  refreshMock.mockReset()
  notesSectionMock.mockReset()
  capturedOnAction = null
})

describe('TasksTable — "notes" row action (spec 0156 D-5, AC-006)', () => {
  it('opens the notes dialog for the row on the tasks thread', () => {
    renderTable()

    expect(capturedOnAction).not.toBeNull()
    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByText('notes-section:tasks:90')).toBeInTheDocument()
    expect(notesSectionMock).toHaveBeenCalledWith(expect.objectContaining({ entityType: 'tasks', entityId: 90 }))
  })

  it('refreshes the grid when the dialog closes, so notes_count stays current', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))
    expect(screen.getByRole('dialog')).toBeInTheDocument()

    fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' })

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(refreshMock).toHaveBeenCalledTimes(1)
  })

  it('refreshes the grid as soon as the thread changes, without closing the dialog', () => {
    renderTable()

    fireEvent.click(screen.getByRole('button', { name: 'trigger-notes' }))

    const props = notesSectionMock.mock.calls.at(-1)?.[0] as { onThreadChanged?: () => void }
    expect(props.onThreadChanged).toBeTypeOf('function')

    props.onThreadChanged?.()

    // The badge follows the write itself: no close needed, and the dialog stays open.
    expect(refreshMock).toHaveBeenCalledTimes(1)
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })
})
