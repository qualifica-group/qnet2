import { beforeEach, describe, expect, it, vi } from 'vitest'
import axios from 'axios'
import { apiClient } from '@/api/client'
import { saveBlob } from '@/lib/download'
import { generateQuoteDocument } from '@/features/quotes/quote-document-api'

/**
 * Spec 0070: `POST /quotes/{id}/document` is a binary streaming response
 * (AC-230), not the standard envelope. Covers the filename fallback and the
 * blob-error normalization that lets `use-quote-document.ts` read a failed
 * request's JSON `message` (AC-303) even though the request was sent with
 * `responseType: 'blob'` (where axios would otherwise hand back an opaque
 * `Blob` as `error.response.data`, not the parsed JSON body).
 */

vi.mock('@/api/client', () => ({
  apiClient: { post: vi.fn() },
}))

vi.mock('@/lib/download', () => ({
  saveBlob: vi.fn(),
  filenameFromContentDisposition: (header: unknown) => {
    const match = typeof header === 'string' ? /filename="?([^";]+)"?/.exec(header) : null
    return match ? match[1] : null
  },
}))

const postMock = vi.mocked(apiClient.post)

beforeEach(() => {
  postMock.mockReset()
  vi.mocked(saveBlob).mockReset()
})

describe('generateQuoteDocument', () => {
  it('downloads the blob using the Content-Disposition filename (AC-230)', async () => {
    const blob = new Blob(['docx-bytes'])
    postMock.mockResolvedValue({
      data: blob,
      headers: { 'content-disposition': 'attachment; filename="QUO-0009.docx"' },
    })

    await generateQuoteDocument(9, 'QUO-0009')

    expect(postMock).toHaveBeenCalledWith('/quotes/9/document', undefined, { responseType: 'blob' })
    expect(saveBlob).toHaveBeenCalledWith(blob, 'QUO-0009.docx')
  })

  it('falls back to "{code}.docx" when Content-Disposition is missing', async () => {
    const blob = new Blob(['docx-bytes'])
    postMock.mockResolvedValue({ data: blob, headers: {} })

    await generateQuoteDocument(9, 'QUO-0009')

    expect(saveBlob).toHaveBeenCalledWith(blob, 'QUO-0009.docx')
  })

  it('parses a blob-shaped 422 error body so the message survives (AC-303)', async () => {
    const errorBody = { success: false, message: 'No layout is available to generate this document.' }
    const blob = new Blob([JSON.stringify(errorBody)], { type: 'application/json' })
    const error = new axios.AxiosError('Unprocessable', '422', undefined, undefined, {
      status: 422,
      data: blob,
    } as never)
    postMock.mockRejectedValue(error)

    await expect(generateQuoteDocument(9, 'QUO-0009')).rejects.toBe(error)
    expect(error.response?.data).toEqual(errorBody)
  })

  it('leaves a non-JSON blob error body untouched', async () => {
    const blob = new Blob(['<html>500</html>'], { type: 'text/html' })
    const error = new axios.AxiosError('Server Error', '500', undefined, undefined, {
      status: 500,
      data: blob,
    } as never)
    postMock.mockRejectedValue(error)

    await expect(generateQuoteDocument(9, 'QUO-0009')).rejects.toBe(error)
    expect(error.response?.data).toBe(blob)
  })
})
