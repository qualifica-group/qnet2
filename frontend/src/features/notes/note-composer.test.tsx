import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog'
import { NoteComposer, type NoteComposerProps } from '@/features/notes/note-composer'
import { RICH_TEXT_NOTE_TEXT_MAX } from '@/components/rich-text/rich-text-constants'
import type { Note } from '@/features/notes/types'

/**
 * Spec 0128 AC-021 — the composer's single write surface is now the shared
 * `RichTextEditor` (D-11) plus F2's `Mention` extension. jsdom has no layout
 * engine: ProseMirror's view queries these when syncing selection/decorations
 * (same stubs as `components/rich-text/rich-text-editor.test.tsx`; never in
 * `src/test/setup.ts`, config-protection hook).
 */
if (!Range.prototype.getBoundingClientRect) {
  Range.prototype.getBoundingClientRect = () =>
    ({ top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0, x: 0, y: 0, toJSON() {} }) as DOMRect
}
if (!Range.prototype.getClientRects) {
  Range.prototype.getClientRects = () => [] as unknown as DOMRectList
}
if (!Element.prototype.getBoundingClientRect) {
  Element.prototype.getBoundingClientRect = () =>
    ({ top: 0, left: 0, right: 0, bottom: 0, width: 0, height: 0, x: 0, y: 0, toJSON() {} }) as DOMRect
}
if (!document.elementFromPoint) {
  document.elementFromPoint = () => null
}

const createMutateAsync = vi.fn()
const updateMutateAsync = vi.fn()
const useCreateNoteMock = vi.fn()
const useUpdateNoteMock = vi.fn()
vi.mock('@/features/notes/use-note-mutations', () => ({
  useCreateNote: (...args: unknown[]) => useCreateNoteMock(...args),
  useUpdateNote: (...args: unknown[]) => useUpdateNoteMock(...args),
}))

const fetchMentionableUsersMock = vi.fn()
vi.mock('@/features/notes/api', () => ({
  fetchMentionableUsers: (...args: unknown[]) => fetchMentionableUsersMock(...args),
  NOTES_MENTIONABLE_PAGE_SIZE: 25,
}))

const ROOT_NOTE: Note = {
  id: 42,
  body: '<p>Original body</p>',
  author: { id: 1, name: 'Mario Rossi', avatar_url: null },
  mentions: [],
  parent_id: null,
  quote_id: null,
  quote: null,
  created_at: '2026-07-20T10:00:00Z',
  edited_at: null,
  can: { update: true, delete: true },
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderComposer(props: Partial<NoteComposerProps> = {}) {
  return render(<NoteComposer entityType="request-management" entityId={1} {...props} />, { wrapper: wrapper() })
}

function bodyEditor() {
  return screen.getByRole('textbox') as HTMLElement
}

/** Simulates a plain-text paste (ProseMirror reads `clipboardData` directly, no real DOM typing needed in jsdom). */
function pasteText(editorDom: Element, text: string) {
  const event = new Event('paste', { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'clipboardData', {
    value: { files: [], getData: (type: string) => (type === 'text/plain' ? text : '') },
  })
  editorDom.dispatchEvent(event)
}

function mentionableUsersPage(items: { id: number; label: string }[]) {
  return { items, pagination: { offset: 0, limit: 25, total: items.length } }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMutateAsync.mockReset()
  updateMutateAsync.mockReset()
  useCreateNoteMock.mockReset()
  useUpdateNoteMock.mockReset()
  fetchMentionableUsersMock.mockReset()
  fetchMentionableUsersMock.mockResolvedValue(mentionableUsersPage([]))
  useCreateNoteMock.mockReturnValue({ mutateAsync: createMutateAsync, isPending: false })
  useUpdateNoteMock.mockReturnValue({ mutateAsync: updateMutateAsync, isPending: false })
})

describe('NoteComposer — submit gating', () => {
  it('disables the submit while the body is empty', () => {
    renderComposer()
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()
  })

  it('enables the submit once the body is non-empty', async () => {
    renderComposer()
    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
  })

  it('keeps the submit disabled while the mutation is in flight, even with a non-empty (preloaded) body', () => {
    useUpdateNoteMock.mockReturnValue({ mutateAsync: updateMutateAsync, isPending: true })
    renderComposer({ editingNote: ROOT_NOTE })
    expect(screen.getByRole('button', { name: 'Save' })).toBeDisabled()
  })
})

describe('NoteComposer — create/reply payload (AC-021)', () => {
  it('sends the body and the mentions derived from the mention nodes in the doc', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    fetchMentionableUsersMock.mockResolvedValue(mentionableUsersPage([{ id: 12, label: 'Alice Verdi' }]))
    renderComposer()

    pasteText(bodyEditor(), '@an')
    const option = await screen.findByRole('option', { name: /Alice Verdi/ })
    fireEvent.mouseDown(option)

    await waitFor(() => expect(bodyEditor().textContent).toContain('@Alice Verdi'))
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() =>
      expect(createMutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({
          entity_type: 'request-management',
          entity_id: 1,
          parent_id: undefined,
          mentions: [12],
          body: expect.stringContaining('data-id="12"'),
        }),
      ),
    )
  })

  it('replying sends the parent_id of the root it was opened under', async () => {
    createMutateAsync.mockResolvedValue({ ...ROOT_NOTE, parent_id: 42 })
    renderComposer({ parentId: 42 })

    pasteText(bodyEditor(), 'A reply')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() =>
      expect(createMutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ parent_id: 42, body: expect.stringContaining('A reply') }),
      ),
    )
  })

  it('clears the draft and calls onDone after a successful root submit', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    const onDone = vi.fn()
    renderComposer({ onDone })

    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(bodyEditor().textContent).toBe(''))
    expect(onDone).toHaveBeenCalledTimes(1)
  })
})

describe('NoteComposer — mention popup inside a modal dialog', () => {
  function renderInDialog() {
    return render(
      <Dialog open>
        <DialogContent>
          <DialogTitle>Notes</DialogTitle>
          <DialogDescription>Thread</DialogDescription>
          <NoteComposer entityType="request-management" entityId={1} />
        </DialogContent>
      </Dialog>,
      { wrapper: wrapper() },
    )
  }

  it('keeps the popup clickable although the modal disables pointer events on the body', async () => {
    fetchMentionableUsersMock.mockResolvedValue(mentionableUsersPage([{ id: 12, label: 'Alice Verdi' }]))
    renderInDialog()

    pasteText(bodyEditor(), '@an')
    const option = await screen.findByRole('option', { name: /Alice Verdi/ })
    const popup = option.closest('[role="listbox"]')?.parentElement

    expect(document.body.style.pointerEvents).toBe('none')
    expect(popup?.style.pointerEvents).toBe('auto')

    fireEvent.mouseDown(option)
    await waitFor(() => expect(bodyEditor().textContent).toContain('@Alice Verdi'))
    expect(screen.getByRole('dialog')).toBeInTheDocument()
  })

  it('inserts the highlighted candidate on Enter', async () => {
    fetchMentionableUsersMock.mockResolvedValue(mentionableUsersPage([{ id: 12, label: 'Alice Verdi' }]))
    renderInDialog()

    pasteText(bodyEditor(), '@an')
    await screen.findByRole('option', { name: /Alice Verdi/ })
    fireEvent.keyDown(bodyEditor(), { key: 'Enter' })

    await waitFor(() => expect(bodyEditor().textContent).toContain('@Alice Verdi'))
  })
})

describe('NoteComposer — edit mode', () => {
  it('preloads the body HTML and PATCHes with the edited body/mentions on save', async () => {
    updateMutateAsync.mockResolvedValue({ ...ROOT_NOTE, body: '<p>Edited body</p>' })
    renderComposer({ editingNote: ROOT_NOTE })

    expect(bodyEditor().textContent).toBe('Original body')

    pasteText(bodyEditor(), ' edited')
    await waitFor(() => expect(bodyEditor().textContent).toContain('edited'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateMutateAsync).toHaveBeenCalledWith({
        noteId: 42,
        payload: { body: expect.stringContaining('edited'), mentions: [] },
      }),
    )
  })
})

describe('NoteComposer — visible text limit (D-5)', () => {
  it('blocks submit past the visible text limit with an accessible error', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    renderComposer()

    pasteText(bodyEditor(), 'a'.repeat(RICH_TEXT_NOTE_TEXT_MAX + 1))
    await waitFor(() => expect(bodyEditor().textContent).toHaveLength(RICH_TEXT_NOTE_TEXT_MAX + 1))
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    expect(await screen.findByText(/can contain at most/i)).toBeInTheDocument()
    expect(createMutateAsync).not.toHaveBeenCalled()
  })
})

describe('NoteComposer — 422 mapping', () => {
  it('maps a 422 onto the body field with the accessible-error triad', async () => {
    createMutateAsync.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { body: ['La nota supera il limite di caratteri.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    renderComposer()
    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    const message = await screen.findByText('La nota supera il limite di caratteri.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(bodyEditor()).toHaveAttribute('aria-invalid', 'true')
    expect(bodyEditor()).toHaveAttribute('aria-describedby', expect.stringContaining(message.id))

    vi.restoreAllMocks()
  })

  it('falls back to a generic message when the failure is not a validated 422', async () => {
    createMutateAsync.mockRejectedValue(new Error('network down'))
    renderComposer()

    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(screen.getByText("Couldn't send. Try again.")).toBeInTheDocument())
  })

  it('maps a 413 onto the body field and keeps the draft so the user can trim an image and retry', async () => {
    createMutateAsync.mockRejectedValue(
      new AxiosError('Payload Too Large', '413', undefined, undefined, {
        status: 413,
        data: { success: false, message: 'Payload Too Large' },
      } as never),
    )

    renderComposer()
    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    const message = await screen.findByText(/too large to save/i)
    expect(message).toHaveAttribute('role', 'alert')
    expect(bodyEditor()).toHaveAttribute('aria-invalid', 'true')
    // The draft is never reset on a failed save (only on success): the user can
    // still remove the offending image and resubmit without retyping the note.
    expect(bodyEditor().textContent).toContain('Hello')
  })
})
