import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@/api/client', () => ({ apiClient: { get: vi.fn(), post: vi.fn(), delete: vi.fn() } }))

import { apiClient } from '@/api/client'
import {
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
