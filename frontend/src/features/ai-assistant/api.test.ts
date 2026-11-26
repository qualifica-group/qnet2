import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))

import { apiClient } from '@/api/client'
import {
  buildAiMessageFormData,
  cancelAiPreview,
  confirmAiPreview,
  fetchAiTurn,
  sendAiMessage,
  createAiConversation,
  deleteAiConversation,
  downloadAiAttachment,
  fetchAiAssistantConfig,
  fetchAiConversation,
  fetchAiConversations,
} from '@/features/ai-assistant/api'

const mocked = vi.mocked(apiClient)

describe('ai-assistant api', () => {
  beforeEach(() => vi.clearAllMocks())

  it('unwraps the envelope for config, detail and create', async () => {
    mocked.get.mockResolvedValueOnce({ data: { data: { configured: true } } })
    expect(await fetchAiAssistantConfig()).toEqual({ configured: true })
    expect(mocked.get).toHaveBeenCalledWith('/ai-assistant/config')

    mocked.get.mockResolvedValueOnce({ data: { data: { id: 3, messages: [] } } })
    expect(await fetchAiConversation(3)).toEqual({ id: 3, messages: [] })
    expect(mocked.get).toHaveBeenLastCalledWith('/ai-assistant/conversations/3')

    mocked.post.mockResolvedValueOnce({ data: { data: { id: 9 } } })
    expect(await createAiConversation()).toEqual({ id: 9 })
  })

  it('returns the paginated list payload as is', async () => {
    const page = { items: [], pagination: { total: 0, offset: 30, limit: 30, total_pages: 0 } }
    mocked.get.mockResolvedValueOnce({ data: page })
    expect(await fetchAiConversations(30)).toBe(page)
    expect(mocked.get).toHaveBeenCalledWith('/ai-assistant/conversations', {
      params: { offset: 30, limit: 30 },
    })
  })

  it('builds the multipart body of the send endpoint with the contract field names', () => {
    const form = buildAiMessageFormData({
      content: 'hi',
      mentions: [{ module: 'companies', id: 5, label: 'ACME' }],
      files: [new File(['x'], 'a.pdf', { type: 'application/pdf' })],
      pageGuideKey: 'general',
      pageGuideText: 'guide',
      locale: 'it',
    })
    expect(form.get('content')).toBe('hi')
    expect(form.get('mentions[0][module]')).toBe('companies')
    expect(form.get('mentions[0][id]')).toBe('5')
    expect(form.getAll('files[]')).toHaveLength(1)
    expect(form.get('page_guide_key')).toBe('general')
    expect(form.get('page_guide_text')).toBe('guide')
    expect(form.get('locale')).toBe('it')
  })

  // REQUIREMENT CHANGED (spec 0433 D-15): the POST answers 202 with the turn, no SSE stream.
  it('posts the message as plain multipart and unwraps the 202 payload', async () => {
    const started = { user_message: { id: 1 }, turn: { id: 2, status: 'queued' } }
    mocked.post.mockResolvedValueOnce({ data: { data: started } })

    const result = await sendAiMessage(7, {
      content: 'hi', mentions: [], files: [], pageGuideKey: null, pageGuideText: null, locale: 'en',
    })

    expect(result).toBe(started)
    const [url, body, config] = mocked.post.mock.calls[0]
    expect(url).toBe('/ai-assistant/conversations/7/messages')
    expect(body).toBeInstanceOf(FormData)
    expect(config).toBeUndefined()
  })

  it('reads one turn from the turn endpoint', async () => {
    mocked.get.mockResolvedValueOnce({ data: { data: { id: 2, status: 'running' } } })
    expect(await fetchAiTurn(7, 2)).toEqual({ id: 2, status: 'running' })
    expect(mocked.get).toHaveBeenLastCalledWith('/ai-assistant/conversations/7/turns/2')
  })

  it('confirms and cancels a preview sending only its id, and unwraps the resumed turn', async () => {
    mocked.post.mockResolvedValueOnce({ data: { data: { id: 5, status: 'queued' } } })
    expect(await confirmAiPreview(1, 5, 'a1b2c3')).toEqual({ id: 5, status: 'queued' })
    expect(mocked.post).toHaveBeenLastCalledWith('/ai-assistant/conversations/1/turns/5/confirm', {
      preview_id: 'a1b2c3',
    })

    mocked.post.mockResolvedValueOnce({ data: { data: { id: 5, status: 'queued' } } })
    await cancelAiPreview(1, 5, 'a1b2c3')
    expect(mocked.post).toHaveBeenLastCalledWith('/ai-assistant/conversations/1/turns/5/cancel', {
      preview_id: 'a1b2c3',
    })
  })

  it('deletes a conversation and downloads an attachment as blob', async () => {
    mocked.delete.mockResolvedValueOnce({})
    await deleteAiConversation(4)
    expect(mocked.delete).toHaveBeenCalledWith('/ai-assistant/conversations/4')

    const blob = new Blob(['x'])
    mocked.get.mockResolvedValueOnce({ data: blob })
    expect(await downloadAiAttachment(1, 2)).toBe(blob)
    expect(mocked.get).toHaveBeenLastCalledWith('/ai-assistant/messages/1/attachments/2', {
      responseType: 'blob',
    })
  })
})
