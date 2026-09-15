import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { makeNote, noteBodyEditor, pasteText, renderSection } from '@/features/notes/notes-section-test-helpers'

/**
 * Split out of `notes-section.test.tsx` (engineering.md §6, file size limit):
 * the quote-scope selectors (spec 0085 amendment) and the scoped-list refetch
 * after a write, both driven by `meta.quotes` on the same `<NotesSection>`.
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

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchNotesMock.mockReset()
  createNoteMock.mockReset()
  updateNoteMock.mockReset()
  deleteNoteMock.mockReset()
  fetchMentionableUsersMock.mockReset()
  fetchMentionableUsersMock.mockResolvedValue({ items: [], pagination: { offset: 0, limit: 25, total: 0 } })
})

/**
 * Spec 0085 amendment (2026-08-06): the host record's Offerte arrive WITH the
 * thread (`meta.quotes`), not from the host. What this covers is that the
 * section mounts filter and destination on its own — the reason every surface
 * (grid row dialog, work panel, detail tab) now has them, with no host passing
 * anything down.
 */
describe('NotesSection — quote scope selectors from meta.quotes', () => {
  const QUOTES = [
    { id: 11, code: 'QUO-0001', title: 'Prima offerta' },
    { id: 12, code: 'QUO-0002', title: 'Seconda offerta' },
  ]

  it('mounts the list filter and the composer destination when the response carries Offerte', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote()],
      meta: { next_cursor: null, has_more: false, quotes: QUOTES },
    })

    renderSection()
    await screen.findByText('A note')

    expect(screen.getByRole('combobox', { name: 'Filter notes by quote' })).toHaveTextContent(
      'All notes',
    )
    expect(screen.getByRole('combobox', { name: 'Note destination' })).toBeInTheDocument()
  })

  it('refetches on the picked Offerta and keeps the filter mounted while the new scope loads', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ body: 'General note' })],
      meta: { next_cursor: null, has_more: false, quotes: QUOTES },
    })

    renderSection()
    await screen.findByText('General note')

    fireEvent.click(screen.getByRole('combobox', { name: 'Filter notes by quote' }))
    fireEvent.click(await screen.findByRole('option', { name: 'QUO-0001' }))

    await waitFor(() =>
      expect(fetchNotesMock).toHaveBeenLastCalledWith(expect.objectContaining({ quoteScope: 11 })),
    )
    // The selector lives in the response the scope change invalidates: it must
    // survive the refetch, or the operator loses the control mid-interaction.
    expect(screen.getByRole('combobox', { name: 'Filter notes by quote' })).toBeInTheDocument()
  })

  it('mounts neither selector when the host record has no Offerta', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote()],
      meta: { next_cursor: null, has_more: false, quotes: [] },
    })

    renderSection()
    await screen.findByText('A note')

    expect(screen.queryByRole('combobox', { name: 'Filter notes by quote' })).not.toBeInTheDocument()
    expect(screen.queryByRole('combobox', { name: 'Note destination' })).not.toBeInTheDocument()
  })

  it('keeps the filter off a section locked on one Offerta (quote detail)', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [makeNote({ quote_id: 11, quote: QUOTES[0] })],
      meta: { next_cursor: null, has_more: false, quotes: QUOTES },
    })

    renderSection(7, { lockedQuoteId: 11 })
    await screen.findByText('A note')

    expect(screen.queryByRole('combobox', { name: 'Filter notes by quote' })).not.toBeInTheDocument()
    // The destination is fixed too: the note being written belongs to that Offerta.
    expect(screen.queryByRole('combobox', { name: 'Note destination' })).not.toBeInTheDocument()
    expect(fetchNotesMock.mock.calls[0][0]).toMatchObject({ quoteScope: 11 })
  })
})

/**
 * La lista si aggiorna da sola dopo una scrittura solo se l'invalidazione
 * colpisce la query MONTATA: dalla spec 0085 la chiave include lo scope, quindi
 * un'invalidazione fissata su `'all'` manca la lista filtrata (o quella bloccata
 * su un'Offerta) e l'operatore vede la propria nota solo ricaricando la pagina.
 */
describe('NotesSection — the list refreshes after a write on any scope', () => {
  const QUOTES = [{ id: 11, code: 'QUO-0001', title: 'Prima offerta' }]

  it('refetches the scoped list after a new root note (section locked on one Offerta)', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [],
      meta: { next_cursor: null, has_more: false, quotes: QUOTES },
    })
    createNoteMock.mockResolvedValue(makeNote({ id: 30, body: 'Scoped note', quote_id: 11 }))

    renderSection(7, { lockedQuoteId: 11 })
    await screen.findByText(/No notes yet/)
    expect(fetchNotesMock).toHaveBeenCalledTimes(1)

    pasteText(noteBodyEditor(), 'Scoped note')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(fetchNotesMock).toHaveBeenCalledTimes(2))
    expect(fetchNotesMock).toHaveBeenLastCalledWith(expect.objectContaining({ quoteScope: 11 }))
  })

  it('refetches the unfiltered list after a new root note', async () => {
    fetchNotesMock.mockResolvedValue({
      data: [],
      meta: { next_cursor: null, has_more: false, quotes: QUOTES },
    })
    createNoteMock.mockResolvedValue(makeNote({ id: 31, body: 'General note' }))

    renderSection()
    await screen.findByText(/No notes yet/)
    expect(fetchNotesMock).toHaveBeenCalledTimes(1)

    pasteText(noteBodyEditor(), 'General note')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(fetchNotesMock).toHaveBeenCalledTimes(2))
  })
})
