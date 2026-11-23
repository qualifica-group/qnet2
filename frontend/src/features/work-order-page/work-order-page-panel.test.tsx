import { act, fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { afterEach, beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Editor } from '@tiptap/react'
import i18n from '@/i18n'
import { AUTOSAVE_DEBOUNCE_MS } from '@/features/work-order-page/use-page-autosave'
import type { PageEditorMenusProps } from '@/features/work-order-page/menus/page-editor-menus'
import type { Poll, TaskRef, WorkOrderPagePayload } from '@/features/work-order-page/types'
import { WorkOrderPagePanel } from '@/features/work-order-page/work-order-page-panel'

const fetchMock = vi.fn<(id: number) => Promise<WorkOrderPagePayload>>()
const saveMock = vi.fn<(id: number, payload: { content: string | null; version: number }) => Promise<WorkOrderPagePayload>>()
const voteMock = vi.fn<(id: number, pollId: number, key: string) => Promise<Poll>>()
const retractMock = vi.fn<(id: number, pollId: number) => Promise<Poll>>()
vi.mock('@/features/work-order-page/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-page/api')>()),
  fetchWorkOrderPage: (id: number) => fetchMock(id),
  saveWorkOrderPage: (id: number, payload: { content: string | null; version: number }) => saveMock(id, payload),
  votePagePoll: (id: number, pollId: number, key: string) => voteMock(id, pollId, key),
  retractPagePollVote: (id: number, pollId: number) => retractMock(id, pollId),
}))

const createTaskMock = vi.fn()
vi.mock('@/features/tasks/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/tasks/api')>()),
  createTask: (payload: unknown) => createTaskMock(payload),
}))

const openViewMock = vi.fn()
vi.mock('@/features/modules/use-module-opener', () => ({
  useModuleOpener: () => ({ openView: openViewMock, sheet: null }),
}))

vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 1 } }) }))

// The real menus are built separately: this stand-in exposes the editor and the insert callbacks.
let editorRef: Editor | null = null
vi.mock('@/features/work-order-page/menus/page-editor-menus', () => ({
  PageEditorMenus: ({ editor, onInsertTask, onInsertPoll }: PageEditorMenusProps) => {
    editorRef = editor
    return (
      <div>
        <button type="button" onClick={onInsertTask}>
          menu-insert-task
        </button>
        <button type="button" onClick={onInsertPoll}>
          menu-insert-poll
        </button>
      </div>
    )
  },
}))

function pagePayload(overrides: Partial<WorkOrderPagePayload> = {}): WorkOrderPagePayload {
  return {
    content: '<p>hello page</p>',
    version: 3,
    updated_at: null,
    updated_by: null,
    can_edit: true,
    tasks: {},
    polls: {},
    ...overrides,
  }
}

function renderPanel(canEditAction = true) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <WorkOrderPagePanel workOrderId={4} canEditAction={canEditAction} />
    </QueryClientProvider>,
  )
}

function conflictError(current: WorkOrderPagePayload): AxiosError {
  return new AxiosError('conflict', 'ERR', undefined, undefined, {
    status: 409,
    data: { success: false, message: 'conflict', code: 'page_version_conflict', data: current },
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  })
}

const poll = (overrides: Partial<Poll> = {}): Poll => ({
  id: 5,
  question: 'Which day?',
  options: [
    { key: 'a', label: 'Monday', votes: 0, voters: [] },
    { key: 'b', label: 'Tuesday', votes: 1, voters: [{ id: 2, name: 'Luca Bianchi' }] },
  ],
  my_vote: null,
  total_votes: 1,
  ...overrides,
})

beforeAll(async () => {
  await i18n.changeLanguage('en')
  // jsdom has no layout: ProseMirror measures rects when it scrolls the caret into view.
  const emptyRects = () => ({ length: 0, item: () => null, [Symbol.iterator]: [][Symbol.iterator] }) as unknown as DOMRectList
  Element.prototype.getClientRects = emptyRects
  Range.prototype.getClientRects = emptyRects
  Range.prototype.getBoundingClientRect = () => new DOMRect()
})

beforeEach(() => {
  vi.useFakeTimers({ shouldAdvanceTime: true })
  vi.clearAllMocks()
  editorRef = null
  fetchMock.mockResolvedValue(pagePayload())
})

afterEach(() => {
  vi.useRealTimers()
})

describe('WorkOrderPagePanel editing and conflict (AC-005, AC-006, AC-022)', () => {
  it('autosaves after the debounce and shows Saving... then Saved', async () => {
    saveMock.mockResolvedValue(pagePayload({ version: 4 }))
    renderPanel()
    await screen.findByText('hello page')

    act(() => {
      editorRef?.commands.insertContent('more')
    })
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))

    expect(saveMock).toHaveBeenCalledTimes(1)
    expect(saveMock.mock.calls[0][1].version).toBe(3)
    expect(await screen.findByText('Saved')).toBeInTheDocument()
  })

  it('shows the conflict banner with Reload, which puts the server text back (AC-005)', async () => {
    saveMock.mockRejectedValue(
      conflictError(pagePayload({ version: 9, content: '<p>server text</p>', updated_by: { id: 2, name: 'Mario Rossi' } })),
    )
    renderPanel()
    await screen.findByText('hello page')

    act(() => {
      editorRef?.commands.insertContent('mine')
    })
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Modified by Mario Rossi')

    fireEvent.click(within(alert).getByRole('button', { name: 'Reload' }))

    expect(await screen.findByText('server text')).toBeInTheDocument()
    expect(screen.queryByRole('alert')).not.toBeInTheDocument()
  })

  it('shows the page read-only, without menus, when the server says can_edit false (AC-006)', async () => {
    fetchMock.mockResolvedValue(pagePayload({ can_edit: false }))
    renderPanel()

    expect(await screen.findByText('Notes are read-only: you cannot edit them.')).toBeInTheDocument()
    expect(screen.getByRole('textbox', { name: 'Work order workspace notes page' })).toHaveAttribute('contenteditable', 'false')
    expect(screen.queryByRole('button', { name: 'menu-insert-task' })).not.toBeInTheDocument()
  })

  it('is read-only without the edit_page action', async () => {
    renderPanel(false)

    expect(await screen.findByText('Notes are read-only: you cannot edit them.')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'menu-insert-poll' })).not.toBeInTheDocument()
  })
})

describe('task block (AC-011)', () => {
  const taskRef = (overrides: Partial<TaskRef> = {}): TaskRef => ({
    id: 33,
    available: true,
    title: 'Write the spec',
    status: { id: 1, name: 'In progress', color: 'blue' },
    end_date: '2026-12-01',
    completed: false,
    assignees: [{ id: 1, name: 'Anna Verdi' }],
    ...overrides,
  })

  it('shows the cited task and opens its detail on click', async () => {
    fetchMock.mockResolvedValue(
      pagePayload({ content: '<div data-type="task-ref" data-task-id="33"></div>', tasks: { '33': taskRef() } }),
    )
    renderPanel()

    const button = await screen.findByRole('button', { name: 'Open task Write the spec' })
    expect(button).toHaveTextContent('In progress')
    expect(button).toHaveTextContent('Anna Verdi')

    fireEvent.click(button)
    expect(openViewMock).toHaveBeenCalledWith({ id: 33, actions: [] })
  })

  it('shows "Task unavailable" without a title for an unavailable task', async () => {
    fetchMock.mockResolvedValue(
      pagePayload({
        content: '<div data-type="task-ref" data-task-id="40"></div>',
        tasks: { '40': taskRef({ id: 40, available: false, title: null, status: null, end_date: null, assignees: [] }) },
      }),
    )
    renderPanel()

    expect(await screen.findByText('Task unavailable')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Open task/ })).not.toBeInTheDocument()
  })

  it('creates a task of the work order from the dialog and inserts its block', async () => {
    createTaskMock.mockResolvedValue({
      id: 33,
      title: 'Write the spec',
      task_status: { id: 1, name: 'To do', color: 'gray' },
      end_date: '2026-12-01',
      completion_date: null,
      assignees: [{ id: 1, name: 'Anna Verdi' }],
    })
    saveMock.mockResolvedValue(pagePayload({ version: 4, tasks: { '33': taskRef() } }))
    renderPanel()
    await screen.findByText('hello page')

    fireEvent.click(screen.getByRole('button', { name: 'menu-insert-task' }))
    const dialog = await screen.findByRole('dialog')
    fireEvent.change(within(dialog).getByLabelText('Title'), { target: { value: 'Write the spec' } })
    fireEvent.change(within(dialog).getByLabelText('Due date'), { target: { value: '2026-12-01' } })
    fireEvent.click(within(dialog).getByRole('button', { name: 'Create task' }))

    await waitFor(() =>
      expect(createTaskMock).toHaveBeenCalledWith(
        expect.objectContaining({ title: 'Write the spec', work_order_id: 4, requester_id: 1, assignee_ids: [1], end_date: '2026-12-01' }),
      ),
    )
    const node = await screen.findByRole('button', { name: 'Open task Write the spec' })
    await waitFor(() => expect(saveMock).toHaveBeenCalled())
    expect(saveMock.mock.calls[0][1].content).toContain('data-task-id="33"')

    fireEvent.click(node)
    expect(openViewMock).toHaveBeenCalledWith({ id: 33, actions: [] })
  })
})

describe('poll block (AC-013 UI)', () => {
  const pollContent = '<div data-type="poll" data-poll-id="5"></div>'

  it('votes, changes and retracts the vote with the matching requests', async () => {
    fetchMock.mockResolvedValue(pagePayload({ content: pollContent, polls: { '5': poll() } }))
    voteMock.mockResolvedValue(poll({ my_vote: 'a', total_votes: 2, options: [
      { key: 'a', label: 'Monday', votes: 1, voters: [{ id: 1, name: 'Anna Verdi' }] },
      { key: 'b', label: 'Tuesday', votes: 1, voters: [{ id: 2, name: 'Luca Bianchi' }] },
    ] }))
    retractMock.mockResolvedValue(poll())
    renderPanel()

    const monday = await screen.findByRole('button', { name: /Monday/ })
    expect(screen.getByRole('button', { name: /Tuesday/ })).toHaveTextContent('Luca Bianchi')

    fireEvent.click(monday)
    await waitFor(() => expect(voteMock).toHaveBeenCalledWith(4, 5, 'a'))
    await waitFor(() => expect(screen.getByRole('button', { name: /Monday/ })).toHaveAttribute('aria-pressed', 'true'))
    expect(screen.getByRole('button', { name: /Monday/ })).toHaveTextContent('Anna Verdi')
    expect(screen.getByText('2 votes in total')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /Monday/ }))
    await waitFor(() => expect(retractMock).toHaveBeenCalledWith(4, 5))
    await waitFor(() => expect(screen.getByRole('button', { name: /Monday/ })).toHaveAttribute('aria-pressed', 'false'))
  })

  it('disables voting on a read-only page', async () => {
    fetchMock.mockResolvedValue(pagePayload({ content: pollContent, polls: { '5': poll() }, can_edit: false }))
    renderPanel()

    expect(await screen.findByRole('button', { name: /Monday/ })).toBeDisabled()
  })
})
