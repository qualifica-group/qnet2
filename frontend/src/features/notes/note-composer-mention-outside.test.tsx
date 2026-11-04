import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, renderHook, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, type AxiosResponse } from 'axios'
import i18n from '@/i18n'
import { NoteComposer, type NoteComposerProps } from '@/features/notes/note-composer'
import { useMentionOutside } from '@/features/notes/use-mention-outside'
import type { MentionableUser, MentionOutsideMeta } from '@/features/notes/types'
import { pasteText } from '@/features/notes/notes-section-test-helpers'

/** Spec 0353 AC-014..AC-017: "Menziona chiunque" nel composer delle note. */

const createMutateAsync = vi.fn()
vi.mock('@/features/notes/use-note-mutations', () => ({
  useCreateNote: () => ({ mutateAsync: createMutateAsync, isPending: false }),
  useUpdateNote: () => ({ mutateAsync: vi.fn(), isPending: false }),
}))

const fetchMentionableUsersMock = vi.fn()
vi.mock('@/features/notes/api', () => ({
  fetchMentionableUsers: (...args: unknown[]) => fetchMentionableUsersMock(...args),
  NOTES_MENTIONABLE_PAGE_SIZE: 25,
}))

const toastSuccess = vi.fn()
const toastError = vi.fn()
vi.mock('sonner', () => ({
  toast: { success: (...args: unknown[]) => toastSuccess(...args), error: (...args: unknown[]) => toastError(...args) },
}))

const ALLOWED_WORK_ORDER: MentionOutsideMeta = { allowed: true, role: 'participant' }
const ALLOWED_TASK: MentionOutsideMeta = { allowed: true, role: 'watcher' }

const INSIDER: MentionableUser = {
  id: 11, label: 'Ivo Dentro', subtitle: null, avatar_url: null, in_record: true, has_module_access: true,
}
const OUTSIDER: MentionableUser = {
  id: 12, label: 'Olga Fuori', subtitle: null, avatar_url: null, in_record: false, has_module_access: true,
}
const OUTSIDER_NO_ACCESS: MentionableUser = {
  id: 13, label: 'Nino Senzaccesso', subtitle: null, avatar_url: null, in_record: false, has_module_access: false,
}

function usersPage(items: MentionableUser[]) {
  return { items, pagination: { offset: 0, limit: 25, total: items.length } }
}

function queryWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function renderComposer(props: Partial<NoteComposerProps> = {}) {
  return render(<NoteComposer entityType="work-orders" entityId={5} {...props} />, { wrapper: queryWrapper() })
}

function bodyEditor() {
  return screen.getByRole('textbox') as HTMLElement
}

async function mention(label: RegExp) {
  pasteText(bodyEditor(), '@o')
  fireEvent.mouseDown(await screen.findByRole('option', { name: label }))
  await waitFor(() => expect(bodyEditor().textContent).toContain('@'))
}

function unprocessable(mentionsMessage: string) {
  const response = { status: 422, data: { message: 'invalid', errors: { mentions: [mentionsMessage] } } }
  return new AxiosError('invalid', 'ERR_BAD_REQUEST', undefined, undefined, response as AxiosResponse)
}

function mentionBody(user: MentionableUser): string {
  return `<p><span data-type="mention" data-id="${user.id}" data-label="${user.label}">@${user.label}</span></p>`
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createMutateAsync.mockReset()
  fetchMentionableUsersMock.mockReset()
  toastSuccess.mockReset()
  toastError.mockReset()
  fetchMentionableUsersMock.mockResolvedValue(usersPage([]))
})

describe('AC-014 — toggle absent when not allowed', () => {
  it.each([[undefined], [{ allowed: false, role: null } as MentionOutsideMeta]])(
    'shows no toggle and searches without scope (%j)',
    async (mentionOutside) => {
      fetchMentionableUsersMock.mockResolvedValue(usersPage([INSIDER]))
      renderComposer({ mentionOutside })

      expect(screen.queryByRole('button', { name: 'Mention anyone' })).not.toBeInTheDocument()
      pasteText(bodyEditor(), '@i')
      await screen.findByRole('option', { name: /Ivo Dentro/ })

      expect(fetchMentionableUsersMock).toHaveBeenCalledWith(expect.objectContaining({ scope: 'record' }))
    },
  )
})

describe('AC-015 — toggle, extended scope and labels', () => {
  it('is off by default, exposes aria-pressed and flips on click', () => {
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER })
    const toggle = screen.getByRole('button', { name: 'Mention anyone' })

    expect(toggle).toHaveAttribute('aria-pressed', 'false')
    fireEvent.click(toggle)
    expect(toggle).toHaveAttribute('aria-pressed', 'true')
  })

  it('searches with scope=extended once on and labels outsiders with the module role', async () => {
    fetchMentionableUsersMock.mockResolvedValue(usersPage([INSIDER, OUTSIDER_NO_ACCESS]))
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER })
    fireEvent.click(screen.getByRole('button', { name: 'Mention anyone' }))

    pasteText(bodyEditor(), '@o')
    const outsider = await screen.findByRole('option', { name: /Nino Senzaccesso/ })

    expect(fetchMentionableUsersMock).toHaveBeenCalledWith(expect.objectContaining({ scope: 'extended' }))
    expect(outsider).toHaveTextContent('Will be added as Participant')
    expect(outsider).toHaveTextContent('Will be notified but cannot open the record')
    expect(screen.getByRole('option', { name: /Ivo Dentro/ })).not.toHaveTextContent('Will be added')
  })

  it('uses the Watcher wording for a task', async () => {
    fetchMentionableUsersMock.mockResolvedValue(usersPage([OUTSIDER]))
    renderComposer({ entityType: 'tasks', mentionOutside: ALLOWED_TASK })
    fireEvent.click(screen.getByRole('button', { name: 'Mention anyone' }))

    pasteText(bodyEditor(), '@o')

    expect(await screen.findByRole('option', { name: /Olga Fuori/ })).toHaveTextContent('Will be added as Watcher')
  })
})

describe('AC-016 — summary derives from the mentions present in the body', () => {
  it('lists the outsider picked from the menu, not the insider', async () => {
    fetchMentionableUsersMock.mockResolvedValue(usersPage([OUTSIDER]))
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER })
    fireEvent.click(screen.getByRole('button', { name: 'Mention anyone' }))

    await mention(/Olga Fuori/)

    const summary = await screen.findByRole('status')
    expect(summary).toHaveTextContent('Will be added as Participant')
    expect(summary).toHaveTextContent('Olga Fuori')
  })

  it('drops an outsider from the summary once the mention leaves the body', async () => {
    const { result } = renderHook(
      () => useMentionOutside({ entityType: 'work-orders', entityId: 5, mentionOutside: ALLOWED_WORK_ORDER }),
      { wrapper: queryWrapper() },
    )
    result.current.source.remember([OUTSIDER, INSIDER])
    result.current.source.pick(OUTSIDER.id)
    result.current.source.pick(INSIDER.id)

    await waitFor(() => expect(result.current.outsidersIn(mentionBody(OUTSIDER))).toEqual([OUTSIDER]))
    expect(result.current.outsidersIn(mentionBody(INSIDER))).toEqual([])
    expect(result.current.outsidersIn('<p>no mention left</p>')).toEqual([])
  })

  it('hydrates the flags of the mentions already in an edited note', async () => {
    fetchMentionableUsersMock.mockResolvedValue(usersPage([OUTSIDER]))
    renderComposer({
      mentionOutside: ALLOWED_WORK_ORDER,
      editingNote: {
        id: 9, body: mentionBody(OUTSIDER), author: { id: 1, name: 'Me', avatar_url: null },
        mentions: [{ id: OUTSIDER.id, name: OUTSIDER.label, avatar_url: null }], parent_id: null,
        quote_id: null, quote: null, created_at: '2026-11-04T10:00:00Z', edited_at: null,
        can: { update: true, delete: true },
      },
    })

    expect(await screen.findByRole('status')).toHaveTextContent('Olga Fuori')
    expect(fetchMentionableUsersMock).toHaveBeenCalledWith(
      expect.objectContaining({ ids: [OUTSIDER.id], scope: 'extended' }),
    )
  })
})

describe('AC-017 — toast, host reload, toggle reset and 422', () => {
  it('toasts the added names, calls onMembersAdded and switches the toggle off', async () => {
    fetchMentionableUsersMock.mockResolvedValue(usersPage([OUTSIDER]))
    createMutateAsync.mockResolvedValue({ note: {}, enlistedUserIds: [OUTSIDER.id] })
    const onMembersAdded = vi.fn()
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER, onMembersAdded })
    const toggle = screen.getByRole('button', { name: 'Mention anyone' })
    fireEvent.click(toggle)
    await mention(/Olga Fuori/)

    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(onMembersAdded).toHaveBeenCalledTimes(1))
    expect(toastSuccess).toHaveBeenCalledWith('Added as Participants: Olga Fuori')
    expect(toggle).toHaveAttribute('aria-pressed', 'false')
  })

  it('does not toast nor reload the host when nobody was added', async () => {
    createMutateAsync.mockResolvedValue({ note: {}, enlistedUserIds: [] })
    const onMembersAdded = vi.fn()
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER, onMembersAdded })
    pasteText(bodyEditor(), 'Hello')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())

    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    await waitFor(() => expect(createMutateAsync).toHaveBeenCalled())
    expect(toastSuccess).not.toHaveBeenCalled()
    expect(onMembersAdded).not.toHaveBeenCalled()
  })

  it('shows the server message on a 422 for mentions and keeps the text', async () => {
    createMutateAsync.mockRejectedValue(unprocessable("The work order's Partecipanti are full (max 12): Olga Fuori cannot be added."))
    renderComposer({ mentionOutside: ALLOWED_WORK_ORDER })
    pasteText(bodyEditor(), 'Keep this text')
    await waitFor(() => expect(screen.getByRole('button', { name: 'Send' })).not.toBeDisabled())

    fireEvent.click(screen.getByRole('button', { name: 'Send' }))

    expect(await screen.findByText(/Partecipanti are full/)).toBeInTheDocument()
    expect(bodyEditor().textContent).toContain('Keep this text')
  })
})
