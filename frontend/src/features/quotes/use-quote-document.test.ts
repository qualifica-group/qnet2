import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { act, renderHook, waitFor } from '@testing-library/react'
import axios from 'axios'
import i18n from '@/i18n'
import { useQuoteDocument } from '@/features/quotes/use-quote-document'

/**
 * Spec 0070 AC-302..AC-304: loading state (shared across callers by id, so
 * the detail button and the table row action can reuse the same hook), no
 * double submit, and error mapping — a 403 shows the translated
 * permission-denied message, a 422 shows the BACKEND's own message (the real
 * case is `no_layout_available`), anything else falls back to a generic one.
 */

const generateQuoteDocumentMock = vi.fn()
vi.mock('@/features/quotes/quote-document-api', () => ({
  generateQuoteDocument: (...args: unknown[]) => generateQuoteDocumentMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (error: unknown) => void
  const promise = new Promise<T>((res, rej) => {
    resolve = res
    reject = rej
  })
  return { promise, resolve, reject }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  generateQuoteDocumentMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

describe('useQuoteDocument', () => {
  it('generates the document and shows a success toast (AC-302)', async () => {
    generateQuoteDocumentMock.mockResolvedValue(undefined)
    const { result } = renderHook(() => useQuoteDocument())

    await act(async () => {
      await result.current.generate(9, 'QUO-0009')
    })

    expect(generateQuoteDocumentMock).toHaveBeenCalledWith(9, 'QUO-0009')
    expect(toastSuccessMock).toHaveBeenCalledWith('PDF generated successfully.')
  })

  it('tracks isGenerating per quote id and blocks a concurrent second call (AC-304)', async () => {
    const first = deferred<void>()
    generateQuoteDocumentMock.mockReturnValueOnce(first.promise)
    const { result } = renderHook(() => useQuoteDocument())

    let generateCall: Promise<void>
    act(() => {
      generateCall = result.current.generate(9, 'QUO-0009')
    })

    await waitFor(() => expect(result.current.isGenerating(9)).toBe(true))
    expect(result.current.isGenerating(3)).toBe(false)

    // A second click while the first is in flight is a no-op: no second call.
    await act(async () => {
      await result.current.generate(9, 'QUO-0009')
    })
    expect(generateQuoteDocumentMock).toHaveBeenCalledTimes(1)

    await act(async () => {
      first.resolve()
      await generateCall
    })

    await waitFor(() => expect(result.current.isGenerating(9)).toBe(false))
  })

  it('shows the translated forbidden message on a 403 (AC-303)', async () => {
    const error = new axios.AxiosError('Forbidden', '403', undefined, undefined, {
      status: 403,
      data: { success: false, message: 'Forbidden' },
    } as never)
    generateQuoteDocumentMock.mockRejectedValue(error)
    const { result } = renderHook(() => useQuoteDocument())

    await act(async () => {
      await result.current.generate(9, 'QUO-0009')
    })

    expect(toastErrorMock).toHaveBeenCalledWith("You don't have permission to generate this document.")
  })

  it("shows the backend's own message on a 422 (AC-303, e.g. no_layout_available)", async () => {
    const error = new axios.AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: { success: false, message: 'No layout is available to generate this document.' },
    } as never)
    generateQuoteDocumentMock.mockRejectedValue(error)
    const { result } = renderHook(() => useQuoteDocument())

    await act(async () => {
      await result.current.generate(9, 'QUO-0009')
    })

    expect(toastErrorMock).toHaveBeenCalledWith('No layout is available to generate this document.')
  })

  it('falls back to a generic message for any other failure', async () => {
    generateQuoteDocumentMock.mockRejectedValue(new Error('network down'))
    const { result } = renderHook(() => useQuoteDocument())

    await act(async () => {
      await result.current.generate(9, 'QUO-0009')
    })

    expect(toastErrorMock).toHaveBeenCalledWith('Unable to generate the document. Please try again.')
  })
})
