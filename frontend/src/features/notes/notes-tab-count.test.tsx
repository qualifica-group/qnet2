import { afterEach, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { NotesTabCount } from '@/features/notes/notes-tab-count'
import type { NotesPage } from '@/features/notes/types'

const fetchNotesMock = vi.fn<(params: unknown) => Promise<NotesPage>>()

vi.mock('@/features/notes/api', () => ({
  NOTES_DEFAULT_PAGE_SIZE: 20,
  fetchNotes: (params: unknown) => fetchNotesMock(params),
}))

function page(total: number): NotesPage {
  return { data: [], meta: { next_cursor: null, has_more: false, total } }
}

function renderCount(quoteScope?: number) {
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={queryClient}>
      <NotesTabCount entityType="tasks" entityId={7} quoteScope={quoteScope} />
    </QueryClientProvider>,
  )
}

afterEach(() => {
  fetchNotesMock.mockReset()
})

describe('NotesTabCount', () => {
  it('shows the thread total of the record (roots plus replies, from meta.total)', async () => {
    fetchNotesMock.mockResolvedValue(page(5))
    renderCount()

    expect(await screen.findByText('5')).toBeInTheDocument()
    expect(fetchNotesMock).toHaveBeenCalledWith(expect.objectContaining({ entityType: 'tasks', entityId: 7, quoteScope: 'all' }))
  })

  it('counts the scope the Offerta locks its notes to', async () => {
    fetchNotesMock.mockResolvedValue(page(2))
    renderCount(12)

    expect(await screen.findByText('2')).toBeInTheDocument()
    expect(fetchNotesMock).toHaveBeenCalledWith(expect.objectContaining({ quoteScope: 12 }))
  })

  it('shows nothing for an empty thread', async () => {
    fetchNotesMock.mockResolvedValue(page(0))
    const { container } = renderCount()

    await vi.waitFor(() => expect(fetchNotesMock).toHaveBeenCalled())
    expect(container).toBeEmptyDOMElement()
  })
})
