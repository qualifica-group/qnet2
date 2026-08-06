import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { NotesSection } from '@/features/notes/notes-section'
import type { Note } from '@/features/notes/types'

/**
 * Spec 0052 AC-070/AC-071 — `<NotesSection>` mounted end-to-end (real
 * `useNotes`/`useNote*Mutations`/`useMentionableUsers` hooks, only the API
 * module is mocked), mirroring `request-work-panel.test.tsx`'s pattern.
 */

const fetchNotesMock = vi.fn()
const createNoteMock = vi.fn()
const updateNoteMock = vi.fn()
const deleteNoteMock = vi.fn()
const fetchMentionableUsersMock = vi.fn()
vi.mock('@/features/notes/api', () => ({
  fetchNotes: (...args: unknown[]) => fetchNotesMock(...args),
  createNote: (...args: unknown[]) => createNoteMock(...args),
  updateNote: (...args: unknown[]) => updateNoteMock(...args),
  deleteNote: (...args: unknown[]) => deleteNoteMock(...args),
  fetchMentionableUsers: (...args: unknown[]) => fetchMentionableUsersMock(...args),
  NOTES_DEFAULT_PAGE_SIZE: 20,
  NOTES_MENTIONABLE_PAGE_SIZE: 25,
}))

function author(id: number, name: string) {
  return { id, name, avatar_url: null }
}

function makeNote(overrides: Partial<Note> = {}): Note {
  return {
    id: 1,
    body: 'A note',
    author: author(1, 'Mario Rossi'),
    mentions: [],
    parent_id: null,
    quote_id: null,
    quote: null,
    created_at: '2026-07-20T09:00:00Z',
    edited_at: null,
    can: { update: false, delete: false },
    ...overrides,
  }
}

function renderSection(entityId = 7) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <NotesSection entityType="request-management" entityId={entityId} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchNotesMock.mockReset()
  createNoteMock.mockReset()
  updateNoteMock.mockReset()
  deleteNoteMock.mockReset()
  fetchMentionableUsersMock.mockReset()
})

describe('NotesSection — thread rendering (AC-070)', () => {
  it('renders the roots in the exact server order, each with author/date/body, and replies nested under their root', async () => {
    const reply = makeNote({
      id: 3,
      body: 'A reply',
      author: author(2, 'Anna Bianchi'),
      parent_id: 2,
      quote_id: null,
      quote: null,
      created_at: '2026-07-21T09:30:00Z',
    })
    const newerRoot = makeNote({
      id: 2,
      body: 'Newer root',
      author: author(1, 'Mario Rossi'),
      created_at: '2026-07-21T09:00:00Z',
      replies: [reply],
    })
    const olderRoot = makeNote({
      id: 1,
      body: 'Older root',
      author: author(3, 'Luca Neri'),
      created_at: '2026-07-20T09:00:00Z',
      replies: [],
    })
    // Server order is deliberately NOT id/date sorted from the component's
    // point of view: the component must render exactly this array order.
    fetchNotesMock.mockResolvedValue({
      data: [newerRoot, olderRoot],
      meta: { next_cursor: null, has_more: false },
    })

    const { container } = renderSection()

    await screen.findByText('Newer root')
    expect(screen.getByText('Older root')).toBeInTheDocument()
    expect(screen.getByText('Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('Luca Neri')).toBeInTheDocument()
    expect(screen.getByText('A reply')).toBeInTheDocument()
    expect(screen.getByText('Anna Bianchi')).toBeInTheDocument()

    // DOM order mirrors array order: "Newer root" before "Older root".
    const html = container.innerHTML
    expect(html.indexOf('Newer root')).toBeLessThan(html.indexOf('Older root'))
    // The reply is rendered after its own root, not before it or under the other root.
    expect(html.indexOf('Newer root')).toBeLessThan(html.indexOf('A reply'))
    expect(html.indexOf('A reply')).toBeLessThan(html.indexOf('Older root'))
  })

  it('shows the empty state when there are no notes', async () => {
    fetchNotesMock.mockResolvedValue({ data: [], meta: { next_cursor: null, has_more: false } })

    renderSection()

    expect(await screen.findByText(/No notes yet/)).toBeInTheDocument()
  })

  it('shows an error state with a retry action when the fetch fails', async () => {
    fetchNotesMock.mockRejectedValue(new Error('network down'))

    renderSection()

    await screen.findByText(/Couldn't load the notes/)
    fetchNotesMock.mockResolvedValue({ data: [], meta: { next_cursor: null, has_more: false } })
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(fetchNotesMock).toHaveBeenCalledTimes(2))
  })

  it('shows "Load more" only when meta.has_more is true, and it fetches the next cursor page', async () => {
    fetchNotesMock.mockResolvedValueOnce({
      data: [makeNote({ id: 1, body: 'First page root' })],
      meta: { next_cursor: 'CURSOR-1', has_more: true },
    })

    renderSection()
    await screen.findByText('First page root')

    const loadMore = screen.getByRole('button', { name: 'Load more' })
    fetchNotesMock.mockResolvedValueOnce({
      data: [makeNote({ id: 2, body: 'Second page root' })],
      meta: { next_cursor: null, has_more: false },
    })
    fireEvent.click(loadMore)

    await screen.findByText('Second page root')
    expect(fetchNotesMock.mock.calls[1][0]).toMatchObject({ cursor: 'CURSOR-1' })
    // No more pages left: the control disappears.
    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument()
  })

  it('does not show "Load more" when meta.has_more is false', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote()],
      meta: { next_cursor: null, has_more: false },
    })

    renderSection()
    await screen.findByText('A note')

    expect(screen.queryByRole('button', { name: 'Load more' })).not.toBeInTheDocument()
  })
})

describe('NotesSection — author-only actions (AC-071, D-8)', () => {
  it('shows edit/delete only when can.update/can.delete are true', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ body: 'Editable root', can: { update: true, delete: true } })],
      meta: { next_cursor: null, has_more: false },
    })

    renderSection()
    await screen.findByText('Editable root')

    expect(screen.getByRole('button', { name: 'Edit note' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Delete note' })).toBeInTheDocument()
  })

  it('hides edit/delete when can.update/can.delete are false', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ body: 'Locked root', can: { update: false, delete: false } })],
      meta: { next_cursor: null, has_more: false },
    })

    renderSection()
    await screen.findByText('Locked root')

    expect(screen.queryByRole('button', { name: 'Edit note' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Delete note' })).not.toBeInTheDocument()
  })

  it('asks for confirmation before deleting, and only deletes once confirmed', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ id: 9, body: 'Deletable root', can: { update: false, delete: true } })],
      meta: { next_cursor: null, has_more: false },
    })
    deleteNoteMock.mockResolvedValue(undefined)

    renderSection()
    await screen.findByText('Deletable root')

    fireEvent.click(screen.getByRole('button', { name: 'Delete note' }))
    const dialog = await screen.findByRole('alertdialog')
    expect(deleteNoteMock).not.toHaveBeenCalled()

    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete note' }))

    await waitFor(() => expect(deleteNoteMock).toHaveBeenCalledWith(9))
  })

  it('does not delete when the confirmation is cancelled', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ id: 9, body: 'Deletable root', can: { update: false, delete: true } })],
      meta: { next_cursor: null, has_more: false },
    })

    renderSection()
    await screen.findByText('Deletable root')

    fireEvent.click(screen.getByRole('button', { name: 'Delete note' }))
    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(deleteNoteMock).not.toHaveBeenCalled()
  })
})

describe('NotesSection — reply flow (AC-071, D-7)', () => {
  it('replying to a root sends the body and the ROOT id as parent_id', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ id: 5, body: 'Thread root', replies: [] })],
      meta: { next_cursor: null, has_more: false },
    })
    createNoteMock.mockResolvedValue(makeNote({ id: 6, parent_id: 5, body: 'A reply text' }))

    renderSection()
    await screen.findByText('Thread root')

    fireEvent.click(screen.getByRole('button', { name: 'Reply' }))

    // The reply composer's field is the second combobox on the page (the
    // root composer's own field is always mounted first).
    const fields = screen.getAllByRole('combobox')
    expect(fields).toHaveLength(2)
    fireEvent.change(fields[1] as HTMLTextAreaElement, { target: { value: 'A reply text' } })

    const sendButtons = screen.getAllByRole('button', { name: 'Send' })
    fireEvent.click(sendButtons[1] as HTMLElement)

    await waitFor(() =>
      expect(createNoteMock).toHaveBeenCalledWith(
        expect.objectContaining({
          entity_type: 'request-management',
          entity_id: 7,
          body: 'A reply text',
          parent_id: 5,
        }),
      ),
    )
  })
})
