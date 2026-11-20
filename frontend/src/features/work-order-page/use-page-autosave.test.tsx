import type { ReactNode } from 'react'
import { act, renderHook } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError, AxiosHeaders } from 'axios'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import type { Editor } from '@tiptap/react'
import { AUTOSAVE_DEBOUNCE_MS, usePageAutosave } from '@/features/work-order-page/use-page-autosave'
import { usePageBlockActions } from '@/features/work-order-page/blocks/use-page-block-actions'
import type { Poll, WorkOrderPagePayload } from '@/features/work-order-page/types'

const saveMock = vi.fn<(id: number, payload: { content: string | null; version: number }) => Promise<WorkOrderPagePayload>>()
const createPollMock = vi.fn<(id: number, payload: unknown) => Promise<Poll>>()
vi.mock('@/features/work-order-page/api', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/work-order-page/api')>()),
  saveWorkOrderPage: (id: number, payload: { content: string | null; version: number }) => saveMock(id, payload),
  createPagePoll: (id: number, payload: unknown) => createPollMock(id, payload),
}))
vi.mock('@/features/auth/use-auth', () => ({ useAuth: () => ({ user: { id: 1 } }) }))

function page(version: number, content: string | null = null): WorkOrderPagePayload {
  return { content, version, updated_at: null, updated_by: { id: 2, name: 'Mario Rossi' }, can_edit: true, tasks: {}, polls: {} }
}

function httpError(status: number, data: unknown): AxiosError {
  return new AxiosError('failed', 'ERR', undefined, undefined, {
    status,
    data,
    statusText: '',
    headers: {},
    config: { headers: new AxiosHeaders() },
  })
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => <QueryClientProvider client={client}>{children}</QueryClientProvider>
}

const applyServerContent = vi.fn()

function renderAutosave() {
  return renderHook(() => usePageAutosave({ workOrderId: 4, initialVersion: 3, applyServerContent }), { wrapper: wrapper() })
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.clearAllMocks()
})

afterEach(() => {
  vi.useRealTimers()
})

describe('usePageAutosave (AC-022)', () => {
  it('saves once after the debounce with the latest content, going saving -> saved', async () => {
    saveMock.mockResolvedValue(page(4))
    const { result } = renderAutosave()

    act(() => result.current.schedule('<p>a</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS - 100))
    act(() => result.current.schedule('<p>ab</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS - 100))
    expect(saveMock).not.toHaveBeenCalled()

    let finish: (value: WorkOrderPagePayload) => void = () => undefined
    saveMock.mockReturnValueOnce(new Promise((resolve) => (finish = resolve)))
    await act(() => vi.advanceTimersByTimeAsync(100))
    expect(saveMock).toHaveBeenCalledTimes(1)
    expect(saveMock).toHaveBeenCalledWith(4, { content: '<p>ab</p>', version: 3 })
    expect(result.current.status).toBe('saving')

    await act(async () => finish(page(4)))
    expect(result.current.status).toBe('saved')
  })

  it('sends one request at a time and then the newest text with the new version', async () => {
    let finish: (value: WorkOrderPagePayload) => void = () => undefined
    saveMock.mockReturnValueOnce(new Promise((resolve) => (finish = resolve)))
    saveMock.mockResolvedValueOnce(page(5))
    const { result } = renderAutosave()

    act(() => result.current.schedule('one'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    act(() => result.current.schedule('two'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    expect(saveMock).toHaveBeenCalledTimes(1)

    await act(async () => finish(page(4)))
    expect(saveMock).toHaveBeenCalledTimes(2)
    expect(saveMock).toHaveBeenLastCalledWith(4, { content: 'two', version: 4 })
    expect(result.current.status).toBe('saved')
  })

  it('keeps the text on a network error and retries it', async () => {
    saveMock.mockRejectedValueOnce(httpError(500, { success: false, message: 'boom' }))
    saveMock.mockResolvedValueOnce(page(4))
    const { result } = renderAutosave()

    act(() => result.current.schedule('<p>keep</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    expect(result.current.status).toBe('error')

    await act(() => result.current.retry())
    expect(saveMock).toHaveBeenLastCalledWith(4, { content: '<p>keep</p>', version: 3 })
    expect(result.current.status).toBe('saved')
  })
})

describe('usePageAutosave conflict and closed work order (AC-005, AC-006)', () => {
  it('suspends on a version conflict and reload() restores the server content and version', async () => {
    saveMock.mockRejectedValueOnce(
      httpError(409, { success: false, message: 'conflict', code: 'page_version_conflict', data: page(9, '<p>server</p>') }),
    )
    const { result } = renderAutosave()

    act(() => result.current.schedule('<p>mine</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    expect(result.current.status).toBe('conflict')
    expect(result.current.conflict?.updated_by?.name).toBe('Mario Rossi')

    act(() => result.current.schedule('<p>mine more</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS * 2))
    expect(saveMock).toHaveBeenCalledTimes(1)

    act(() => result.current.reload())
    expect(applyServerContent).toHaveBeenCalledWith('<p>server</p>')
    expect(result.current.status).toBe('idle')

    saveMock.mockResolvedValueOnce(page(10))
    act(() => result.current.schedule('<p>after</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    expect(saveMock).toHaveBeenLastCalledWith(4, { content: '<p>after</p>', version: 9 })
  })

  it('turns read-only on a 409 without conflict code (work order closed)', async () => {
    saveMock.mockRejectedValueOnce(httpError(409, { success: false, message: 'closed' }))
    const { result } = renderAutosave()

    act(() => result.current.schedule('x'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))

    expect(result.current.isClosed).toBe(true)
    expect(result.current.conflict).toBeNull()
  })
})

describe('poll insertion sequence (server prunes polls the saved content does not cite)', () => {
  it('waits for the save in flight, creates the poll, inserts the node, then saves without debounce', async () => {
    const order: string[] = []
    let finishFirstSave: (value: WorkOrderPagePayload) => void = () => undefined
    saveMock.mockImplementationOnce(() => {
      order.push('save:start')
      return new Promise((resolve) => (finishFirstSave = resolve))
    })
    saveMock.mockImplementationOnce((_id, payload) => {
      order.push(`save:${payload.content}`)
      return Promise.resolve(page(5))
    })
    createPollMock.mockImplementation(() => {
      order.push('poll:create')
      return Promise.resolve({ id: 7, question: 'q', options: [], my_vote: null, total_votes: 0 })
    })

    // Inserting the node makes the real editor emit `update`, i.e. schedule the new content.
    const editor = {
      chain: () => ({
        focus: () => ({
          insertPoll: () => ({
            run: () => {
              order.push('node:insert')
              result.current.autosave.schedule('<p>typed</p><poll>')
            },
          }),
        }),
      }),
    } as unknown as Editor
    const { result } = renderHook(
      () => {
        const autosave = usePageAutosave({ workOrderId: 4, initialVersion: 3, applyServerContent })
        const blocks = usePageBlockActions({ workOrderId: 4, editor, flush: autosave.flush })
        return { autosave, blocks }
      },
      { wrapper: wrapper() },
    )

    act(() => result.current.autosave.schedule('<p>typed</p>'))
    await act(() => vi.advanceTimersByTimeAsync(AUTOSAVE_DEBOUNCE_MS))
    expect(order).toEqual(['save:start'])

    // The user creates the poll while that save is still running.
    let inserted: Promise<void> = Promise.resolve()
    act(() => {
      inserted = result.current.blocks.insertPoll({ question: 'q', options: ['a', 'b'] })
    })
    await act(() => vi.advanceTimersByTimeAsync(0))
    expect(createPollMock).not.toHaveBeenCalled()

    saveMock.mockImplementationOnce((_id, payload) => {
      order.push(`save:${payload.content}`)
      return Promise.resolve(page(6))
    })
    await act(async () => {
      finishFirstSave(page(4))
      await inserted
    })

    expect(order.slice(0, 3)).toEqual(['save:start', 'poll:create', 'node:insert'])
    expect(order.at(-1)).toBe('save:<p>typed</p><poll>')
  })
})
