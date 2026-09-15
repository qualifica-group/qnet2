import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { NotesSection } from '@/features/notes/notes-section'
import type { Note } from '@/features/notes/types'

/**
 * Shared scaffolding for `notes-section*.test.tsx` (spec 0128): the body field
 * is now the shared `RichTextEditor` (D-11), a contentEditable
 * `role="textbox"`, not a native textarea — jsdom has no layout engine, so it
 * needs the same ProseMirror stubs as `rich-text-editor.test.tsx` (never in
 * `src/test/setup.ts`, config-protection hook). Each test file still declares
 * its own `vi.mock('@/features/notes/api', ...)`: mocks are hoisted per file
 * in Vitest and cannot be shared across modules.
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

/** Simulates a plain-text paste (ProseMirror reads `clipboardData` directly, no real DOM typing needed in jsdom). */
export function pasteText(editorDom: Element, text: string) {
  const event = new Event('paste', { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'clipboardData', {
    value: { files: [], getData: (type: string) => (type === 'text/plain' ? text : '') },
  })
  editorDom.dispatchEvent(event)
}

/**
 * `role="textbox"` also matches a note's own read-only `RichTextContent`
 * (jsdom's role mapping doesn't key off `contenteditable`'s value), so a
 * mounted thread mixes editable composer fields with read-only bodies.
 * Editable ones are the only `contenteditable="true"` matches.
 */
export function noteBodyEditors(): HTMLElement[] {
  return screen
    .getAllByRole('textbox')
    .filter((element) => element.getAttribute('contenteditable') === 'true')
}

export function noteBodyEditor(): HTMLElement {
  const [editor] = noteBodyEditors()
  if (!editor) {
    throw new Error('No editable note body field found')
  }
  return editor
}

export function author(id: number, name: string) {
  return { id, name, avatar_url: null }
}

export function makeNote(overrides: Partial<Note> = {}): Note {
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

export function renderSection(
  entityId = 7,
  props: { lockedQuoteId?: number | null; onThreadChanged?: () => void } = {},
) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <NotesSection entityType="request-management" entityId={entityId} {...props} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}
