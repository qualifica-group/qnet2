import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { NoteComposer, type NoteComposerProps } from '@/features/notes/note-composer'
import type { Note } from '@/features/notes/types'

/** Spec 0052 AC-071/AC-073 — the single write surface for new roots, replies and edits. */

const createMutateAsync = vi.fn()
const updateMutateAsync = vi.fn()
const useCreateNoteMock = vi.fn()
const useUpdateNoteMock = vi.fn()
vi.mock('@/features/notes/use-note-mutations', () => ({
  useCreateNote: (...args: unknown[]) => useCreateNoteMock(...args),
  useUpdateNote: (...args: unknown[]) => useUpdateNoteMock(...args),
}))

const ROOT_NOTE: Note = {
  id: 42,
  body: 'Original body',
  author: { id: 1, name: 'Mario Rossi', avatar_url: null },
  mentions: [],
  parent_id: null,
  quote_id: null,
  quote: null,
  created_at: '2026-07-20T10:00:00Z',
  edited_at: null,
  can: { update: true, delete: true },
}

function renderComposer(props: Partial<NoteComposerProps> = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <NoteComposer entityType="request-management" entityId={1} {...props} />
    </QueryClientProvider>,
  )
}

function bodyField() {
  return screen.getByRole('combobox') as HTMLTextAreaElement
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMutateAsync.mockReset()
  updateMutateAsync.mockReset()
  useCreateNoteMock.mockReset()
  useUpdateNoteMock.mockReset()
  useCreateNoteMock.mockReturnValue({ mutateAsync: createMutateAsync, isPending: false })
  useUpdateNoteMock.mockReturnValue({ mutateAsync: updateMutateAsync, isPending: false })
})

describe('NoteComposer — submit gating', () => {
  it('disables the submit while the body is empty', () => {
    renderComposer()
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()
  })

  it('enables the submit once the body is non-empty', () => {
    renderComposer()
    fireEvent.change(bodyField(), { target: { value: 'Hello' } })
    expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled()
  })

  it('keeps the submit disabled while the mutation is in flight, even with a non-empty body', () => {
    useCreateNoteMock.mockReturnValue({ mutateAsync: createMutateAsync, isPending: true })
    renderComposer()
    fireEvent.change(bodyField(), { target: { value: 'Hello' } })
    expect(screen.getByRole('button', { name: 'Send' })).toBeDisabled()
  })
})

describe('NoteComposer — create/reply payload (AC-071)', () => {
  it('sends the body and the mentions array derived from the tokens', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    renderComposer()

    fireEvent.change(bodyField(), { target: { value: 'Hello @[Alice Verdi](user:12)' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() =>
      expect(createMutateAsync).toHaveBeenCalledWith({
        entity_type: 'request-management',
        entity_id: 1,
        body: 'Hello @[Alice Verdi](user:12)',
        parent_id: undefined,
        mentions: [12],
      }),
    )
  })

  it('replying sends the parent_id of the root it was opened under', async () => {
    createMutateAsync.mockResolvedValue({ ...ROOT_NOTE, parent_id: 42 })
    renderComposer({ parentId: 42 })

    fireEvent.change(bodyField(), { target: { value: 'A reply' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() =>
      expect(createMutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ parent_id: 42, body: 'A reply' }),
      ),
    )
  })

  it('clears the draft and calls onDone after a successful root submit', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    const onDone = vi.fn()
    renderComposer({ onDone })

    fireEvent.change(bodyField(), { target: { value: 'Hello' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(bodyField()).toHaveValue(''))
    expect(onDone).toHaveBeenCalledTimes(1)
  })
})

describe('NoteComposer — mention badges', () => {
  it('shows a badge per mentioned user and keeps the field free of raw tokens', () => {
    renderComposer()

    fireEvent.change(bodyField(), { target: { value: 'Hi @[Alice Verdi](user:12)' } })

    expect(screen.getByRole('button', { name: "Remove Alice Verdi's mention" })).toBeInTheDocument()
    // The badge itself opens the profile, same affordance as the table person columns.
    expect(screen.getByRole('button', { name: "View Alice Verdi's profile" })).toBeInTheDocument()
    expect(bodyField()).toHaveValue('Hi @Alice Verdi')
  })

  it('dismissing a badge strips the mention from both the body and the payload', async () => {
    createMutateAsync.mockResolvedValue(ROOT_NOTE)
    renderComposer()

    fireEvent.change(bodyField(), { target: { value: 'Hi @[Alice Verdi](user:12) ciao' } })
    fireEvent.click(screen.getByRole('button', { name: "Remove Alice Verdi's mention" }))

    expect(bodyField()).toHaveValue('Hi ciao')
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))
    await waitFor(() =>
      expect(createMutateAsync).toHaveBeenCalledWith(
        expect.objectContaining({ body: 'Hi ciao', mentions: [] }),
      ),
    )
  })
})

describe('NoteComposer — edit mode', () => {
  it('pre-fills the body and PATCHes with the edited body/mentions on save', async () => {
    updateMutateAsync.mockResolvedValue({ ...ROOT_NOTE, body: 'Edited body' })
    renderComposer({ editingNote: ROOT_NOTE })

    expect(bodyField()).toHaveValue('Original body')

    fireEvent.change(bodyField(), { target: { value: 'Edited body' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() =>
      expect(updateMutateAsync).toHaveBeenCalledWith({
        noteId: 42,
        payload: { body: 'Edited body', mentions: [] },
      }),
    )
  })
})

describe('NoteComposer — 422 mapping (AC-073)', () => {
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
    fireEvent.change(bodyField(), { target: { value: 'Hello' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() =>
      expect(screen.getByText('La nota supera il limite di caratteri.')).toBeInTheDocument(),
    )
    const message = screen.getByText('La nota supera il limite di caratteri.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(bodyField()).toHaveAttribute('aria-invalid', 'true')
    expect(bodyField()).toHaveAttribute('aria-describedby', expect.stringContaining(message.id))
    // The message is exactly the server-provided string: no internal class/exception leakage appended.
    expect(message.textContent).toBe('La nota supera il limite di caratteri.')

    vi.restoreAllMocks()
  })

  it('falls back to a generic message when the failure is not a validated 422', async () => {
    createMutateAsync.mockRejectedValue(new Error('network down'))
    renderComposer()

    fireEvent.change(bodyField(), { target: { value: 'Hello' } })
    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(screen.getByText("Couldn't send. Try again.")).toBeInTheDocument())
  })
})
