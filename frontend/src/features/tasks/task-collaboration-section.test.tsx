import { beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ReactNode } from 'react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { MemoryRouter } from 'react-router-dom'
import i18n from '@/i18n'
import { ConfirmContext, type ConfirmFn } from '@/components/confirm-dialog-context'
import { TaskCollaborationSection } from '@/features/tasks/task-collaboration-section'
import { TaskDetailView } from '@/features/tasks/task-detail'
import { FULL_ACCESS_PERMISSIONS, taskDetailWithPermissions } from '@/features/tasks/task-fixtures'
import type { ResourcePermissions } from '@/features/authorization/types'

/*
 * Collaboration card of the Task detail (spec 0117, D-11, AC-023..AC-027).
 *
 * The three sections are agnostic components owned by other features: what
 * belongs to the Task is WHICH tab mounts and WITH WHICH props. They are
 * stubbed down to their props for exactly that reason — a real mount would
 * test `NotesSection`/`DocumentsSection` again, and drag their network paths
 * into this suite.
 *
 * `TabsContent` is stubbed to render unconditionally, the idiom this repo
 * already uses in `contract-detail.test.tsx`: Radix mounts only the ACTIVE
 * panel and activating a trigger by pointer is unreliable under jsdom (no
 * `@testing-library/user-event` here). What is under test is this view's own
 * wiring — which tab exists and which props reach it — not Radix's switching
 * mechanics. `Tabs`/`TabsList`/`TabsTrigger` stay real, so the "tab" role
 * queries still exercise them.
 */

let granted: string[] = []

// Related-record links open their target in a modal through `useModuleOpener`,
// whose mode resolver reads the authenticated user's preference. The preference
// is not what these tests are about, so the resolver is stubbed rather than
// dragging an AuthProvider into every render.
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => granted.includes(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/ui/tabs', async (importOriginal) => {
  const actual = await importOriginal<typeof import('@/components/ui/tabs')>()
  return {
    ...actual,
    TabsContent: ({ children }: { children: ReactNode }) => <div>{children}</div>,
  }
})

vi.mock('@/features/notes/notes-section', () => ({
  NotesSection: (props: { entityType: string; entityId: number; showHeader?: boolean }) => (
    <div
      data-testid="notes-section"
      data-entity-type={props.entityType}
      data-entity-id={props.entityId}
      data-show-header={String(props.showHeader)}
    />
  ),
}))

vi.mock('@/features/attachments/documents-section', () => ({
  DocumentsSection: (props: {
    resource: string
    id: number
    canUpload: boolean
    canDelete: boolean
  }) => (
    <div
      data-testid="documents-section"
      data-resource={props.resource}
      data-id={props.id}
      data-can-upload={String(props.canUpload)}
      data-can-delete={String(props.canDelete)}
    />
  ),
}))

vi.mock('@/features/activity-log/activity-log-section', () => ({
  ActivityLogSection: (props: { resource: string; id: number }) => (
    <div data-testid="activity-log-section" data-resource={props.resource} data-id={props.id} />
  ),
}))

vi.mock('@/features/time-entries/task/task-time-entries-section', () => ({
  TaskTimeEntriesSection: (props: { taskId: number }) => (
    <div data-testid="time-entries-section" data-task-id={props.taskId} />
  ),
}))

/** Controlled per-test by reassignment, mirrors `granted` (spec 0122 MT-F6). */
let timeEntriesQueryResult: { data: { total_minutes: number } | undefined } = { data: undefined }

vi.mock('@/features/time-entries/task/use-task-time-entries', () => ({
  useTaskTimeEntries: () => timeEntriesQueryResult,
}))

const label = (key: string) => i18n.t(key)

function permissionsWith(actions: ResourcePermissions['actions']): ResourcePermissions {
  return { ...FULL_ACCESS_PERMISSIONS, actions }
}

const ALL_TABS = { view_documents: true, view_activity: true }

beforeEach(() => {
  granted = []
  timeEntriesQueryResult = { data: undefined }
})

describe('gating', () => {
  it('AC-023: with all three gates open the card shows the three tabs', () => {
    granted = ['tasks.view']
    render(<TaskCollaborationSection task={taskDetailWithPermissions({ permissions: permissionsWith(ALL_TABS) })} />)

    expect(screen.getByRole('tab', { name: label('notes.section.title') })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: label('attachments.title') })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: label('activityLog.title') })).toBeInTheDocument()
  })

  it('AC-024: with no gate open the whole card is absent', () => {
    granted = []
    render(<TaskCollaborationSection task={taskDetailWithPermissions({ permissions: permissionsWith({}) })} />)

    expect(screen.queryByRole('tab')).not.toBeInTheDocument()
    expect(screen.queryByTestId('notes-section')).not.toBeInTheDocument()
    expect(screen.queryByTestId('documents-section')).not.toBeInTheDocument()
    expect(screen.queryByTestId('activity-log-section')).not.toBeInTheDocument()
  })

  it('AC-025: with only view_documents the documents tab is the only one', () => {
    granted = []
    render(
      <TaskCollaborationSection
        task={taskDetailWithPermissions({ permissions: permissionsWith({ view_documents: true }) })}
      />,
    )

    const tabs = screen.getAllByRole('tab')
    expect(tabs).toHaveLength(1)
    expect(tabs[0]).toHaveTextContent(label('attachments.title'))
    expect(screen.getByTestId('documents-section')).toBeInTheDocument()
  })

  it('AC-024: notes are gated on tasks.view alone, documents on the server flag alone', () => {
    granted = ['tasks.view']
    render(
      <TaskCollaborationSection
        task={taskDetailWithPermissions({ permissions: permissionsWith({ view_documents: false }) })}
      />,
    )

    expect(screen.getByRole('tab', { name: label('notes.section.title') })).toBeInTheDocument()
    expect(screen.queryByRole('tab', { name: label('attachments.title') })).not.toBeInTheDocument()
  })

  it('MT-F6/AC-040: the Segnatempo tab is absent without time-entries.viewAny', () => {
    granted = []
    render(<TaskCollaborationSection task={taskDetailWithPermissions({ permissions: permissionsWith({}) })} />)

    expect(screen.queryByRole('tab', { name: /Segnatempo/ })).not.toBeInTheDocument()
  })

  it('MT-F6/AC-040: with time-entries.viewAny the Segnatempo tab shows the total-minutes badge', () => {
    granted = ['time-entries.viewAny']
    timeEntriesQueryResult = { data: { total_minutes: 90 } }
    render(<TaskCollaborationSection task={taskDetailWithPermissions({ permissions: permissionsWith({}) })} />)

    const tab = screen.getByRole('tab', { name: /Segnatempo/ })
    expect(tab).toHaveTextContent('1h 30m')
  })
})

describe('props of the mounted sections', () => {
  it('AC-027: NotesSection receives the plural domain slug and no header', () => {
    granted = ['tasks.view']
    const task = taskDetailWithPermissions({ permissions: permissionsWith(ALL_TABS) })
    render(<TaskCollaborationSection task={task} />)

    const notes = screen.getByTestId('notes-section')
    expect(notes).toHaveAttribute('data-entity-type', 'tasks')
    expect(notes).toHaveAttribute('data-entity-id', String(task.id))
    expect(notes).toHaveAttribute('data-show-header', 'false')
  })

  it('AC-026: DocumentsSection receives the singular morph alias and the attachment abilities', () => {
    granted = ['tasks.view', 'attachments.create']
    const task = taskDetailWithPermissions({ permissions: permissionsWith({ view_documents: true }) })
    render(<TaskCollaborationSection task={task} />)

    const documents = screen.getByTestId('documents-section')
    expect(documents).toHaveAttribute('data-resource', 'task')
    expect(documents).toHaveAttribute('data-id', String(task.id))
    expect(documents).toHaveAttribute('data-can-upload', 'true')
    // `attachments.delete` was NOT granted: the two flags are read separately.
    expect(documents).toHaveAttribute('data-can-delete', 'false')
  })

  it('MT-F6: TaskTimeEntriesSection receives the task id', () => {
    granted = ['time-entries.viewAny']
    const task = taskDetailWithPermissions({ permissions: permissionsWith({}) })
    render(<TaskCollaborationSection task={task} />)

    expect(screen.getByTestId('time-entries-section')).toHaveAttribute('data-task-id', String(task.id))
  })
})

describe('integration with the detail', () => {
  it('AC-023: the detail renders the activity log inside the card and nowhere else', () => {
    granted = ['tasks.view']
    // `TaskActionsBar` inside the detail needs the confirm provider and the
    // query client its mutations hang off. One client per test, never per
    // render (frontend.md §10).
    const confirm: ConfirmFn = async () => true
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

    render(
      <MemoryRouter>
        <QueryClientProvider client={queryClient}>
          <ConfirmContext.Provider value={confirm}>
            <TaskDetailView
            task={taskDetailWithPermissions({ permissions: permissionsWith(ALL_TABS) })}
              onOpenSubtask={vi.fn()}
              onCreateSubtask={vi.fn()}
            />
          </ConfirmContext.Provider>
        </QueryClientProvider>
      </MemoryRouter>,
    )

    // Before spec 0117 the log rendered in a block of its own at the bottom
    // of the detail card. It now lives in the tab strip and ONLY there: a
    // second mount would mean the old block survived the move.
    const logs = screen.getAllByTestId('activity-log-section')
    expect(logs).toHaveLength(1)
    expect(logs[0]).toHaveAttribute('data-resource', 'tasks')
    expect(screen.getByRole('tab', { name: label('activityLog.title') })).toBeInTheDocument()
  })
})
