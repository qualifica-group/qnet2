import { beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, screen, waitFor, within } from '@testing-library/react'
import { useState } from 'react'
import { AiStreamHttpError } from '@/features/ai-assistant/ai-sse-stream'
import { AiChatPanel } from '@/features/ai-assistant/components/ai-chat-panel'
import { buildAiConfig, renderWithProviders } from '@/features/ai-assistant/components/ai-test-support'
import type { AiAssistantConfig } from '@/features/ai-assistant/types'

vi.mock('@/features/ai-assistant/api', () => ({
  AI_CONVERSATIONS_PAGE_SIZE: 30,
  fetchAiAssistantConfig: vi.fn(),
  fetchAiConversation: vi.fn(),
  fetchAiConversations: vi.fn(),
  createAiConversation: vi.fn(),
  deleteAiConversation: vi.fn(),
  downloadAiAttachment: vi.fn(),
}))
vi.mock('@/features/ai-assistant/ai-sse-stream', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/features/ai-assistant/ai-sse-stream')>()),
  streamAiMessage: vi.fn(),
}))
vi.mock('@/features/ai-assistant/hooks/use-ai-page-guide', () => ({
  useAiPageGuide: vi.fn(() => ({ locale: 'it', guideKey: 'general', guideText: 'GUIDE TEXT' })),
}))
vi.mock('@/features/for-select/api', () => ({ fetchForSelect: vi.fn() }))

import { createAiConversation, fetchAiAssistantConfig } from '@/features/ai-assistant/api'
import { streamAiMessage } from '@/features/ai-assistant/ai-sse-stream'
import { fetchForSelect } from '@/features/for-select/api'

const CONVERSATION = { id: 7, title: '', last_message_at: null, created_at: '2026-11-02T10:00:00Z' }

function Harness({ config }: { config: AiAssistantConfig }) {
  vi.mocked(fetchAiAssistantConfig).mockResolvedValue(config)
  const [id, setId] = useState<number | null>(null)
  return <AiChatPanel variant="widget" conversationId={id} onConversationChange={setId} />
}

async function renderComposer(config = buildAiConfig()) {
  renderWithProviders(<Harness config={config} />)
  const textbox = await screen.findByRole('textbox', { name: 'Scrivi un messaggio' })
  return { textbox: textbox as HTMLTextAreaElement }
}

function fileInput(): HTMLInputElement {
  return document.querySelector('input[type="file"]') as HTMLInputElement
}

function typeInto(textbox: HTMLTextAreaElement, value: string) {
  fireEvent.change(textbox, { target: { value } })
}

beforeEach(() => {
  vi.mocked(createAiConversation).mockResolvedValue(CONVERSATION)
  vi.mocked(streamAiMessage).mockReset()
  vi.mocked(fetchForSelect).mockReset()
})

describe('AiComposer limits and accessible errors (AC-023)', () => {
  it('blocks an oversized file with the aria triad on the field', async () => {
    await renderComposer()
    const big = new File([new Uint8Array(2 * 1024 * 1024)], 'big.pdf', { type: 'application/pdf' })

    fireEvent.change(fileInput(), { target: { files: [big] } })

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('"big.pdf" supera la dimensione massima di 1 MB')
    const attach = screen.getByRole('button', { name: 'Allega file' })
    expect(attach).toHaveAttribute('aria-invalid', 'true')
    expect(attach).toHaveAttribute('aria-describedby', alert.id)
  })

  it('rejects a disallowed type and a count over max_files', async () => {
    await renderComposer()
    const exe = new File(['x'], 'run.exe', { type: 'application/x-msdownload' })
    fireEvent.change(fileInput(), { target: { files: [exe] } })
    expect(await screen.findByRole('alert')).toHaveTextContent('"run.exe" non è un formato ammesso')

    fireEvent.click(screen.getByRole('button', { name: 'Rimuovi run.exe' }))
    await waitFor(() => expect(screen.queryByRole('alert')).not.toBeInTheDocument())

    const pdfs = ['a', 'b', 'c'].map((name) => new File(['x'], `${name}.pdf`, { type: 'application/pdf' }))
    fireEvent.change(fileInput(), { target: { files: pdfs } })
    expect(await screen.findByRole('alert')).toHaveTextContent('al massimo 2 file')
  })

  it('does not send over the limits and marks the content field when too long', async () => {
    await renderComposer(buildAiConfig({ limits: { ...buildAiConfig().limits, max_message_chars: 5 } }))
    const textbox = screen.getByRole('textbox', { name: 'Scrivi un messaggio' }) as HTMLTextAreaElement
    typeInto(textbox, 'troppo lungo')

    fireEvent.click(screen.getByRole('button', { name: 'Invia' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('supera i 5 caratteri')
    expect(textbox).toHaveAttribute('aria-invalid', 'true')
    expect(textbox.getAttribute('aria-describedby')).toContain(alert.id)
    expect(streamAiMessage).not.toHaveBeenCalled()
  })
})

describe('AiComposer mentions (AC-022)', () => {
  it('opens the module list on "@" with only the allowed modules and picks with the keyboard', async () => {
    const { textbox } = await renderComposer()
    vi.mocked(fetchForSelect).mockResolvedValue({
      items: [
        { id: 11, label: 'Task uno', subtitle: 'Alpha' },
        { id: 12, label: 'Task due' },
      ],
      export_link: null,
      pagination: { total: null, offset: 0, limit: 8, total_pages: null },
    })

    typeInto(textbox, '@')
    const modules = await screen.findByRole('listbox', { name: 'Scegli il modulo' })
    expect(within(modules).getAllByRole('option').map((option) => option.textContent)).toEqual(['Aziende', 'Task'])

    fireEvent.keyDown(textbox, { key: 'ArrowDown' })
    fireEvent.keyDown(textbox, { key: 'Enter' })

    expect(textbox.value).toBe('')
    const search = await screen.findByRole('combobox', { name: 'Cerca nel modulo Task' })
    expect(screen.getByText('Digita almeno 2 caratteri.')).toBeInTheDocument()
    expect(fetchForSelect).not.toHaveBeenCalled()

    fireEvent.change(search, { target: { value: 'ta' } })
    const results = await screen.findByRole('listbox', { name: 'Risultati della ricerca' })
    expect(within(results).getAllByRole('option')).toHaveLength(2)
    expect(fetchForSelect).toHaveBeenCalledWith('tasks', expect.objectContaining({ search: 'ta' }))

    fireEvent.keyDown(search, { key: 'ArrowDown' })
    fireEvent.keyDown(search, { key: 'Enter' })

    expect(await screen.findByRole('button', { name: 'Rimuovi la menzione Task due' })).toBeInTheDocument()
    expect(screen.queryByRole('combobox')).not.toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Rimuovi la menzione Task due' }))
    expect(screen.queryByRole('button', { name: 'Rimuovi la menzione Task due' })).not.toBeInTheDocument()
  })

  it('closes the module list with Escape and ignores "@" inside a word', async () => {
    const { textbox } = await renderComposer()

    typeInto(textbox, 'mail@')
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()

    typeInto(textbox, 'ciao @')
    expect(await screen.findByRole('listbox', { name: 'Scegli il modulo' })).toBeInTheDocument()
    fireEvent.keyDown(textbox, { key: 'Escape' })
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })
})

describe('AiComposer send (AC-021, AC-024)', () => {
  it('keeps draft, files and mentions and shows a translated alert when the send fails', async () => {
    const { textbox } = await renderComposer()
    vi.mocked(streamAiMessage).mockImplementation(() =>
      (async function* () {
        yield* []
        throw new AiStreamHttpError(503, 'Service unavailable (server English text)')
      })(),
    )
    typeInto(textbox, 'Ciao assistente')
    fireEvent.change(fileInput(), { target: { files: [new File(['x'], 'nota.pdf', { type: 'application/pdf' })] } })

    fireEvent.click(screen.getByRole('button', { name: 'Invia' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent("L'assistente AI non è configurato")
    expect(alert).not.toHaveTextContent('Service unavailable')
    expect(textbox.value).toBe('Ciao assistente')
    expect(screen.getByRole('button', { name: 'Rimuovi nota.pdf' })).toBeInTheDocument()
  })

  it('shows the generic message for an interrupted stream, never the server text', async () => {
    const { textbox } = await renderComposer()
    vi.mocked(streamAiMessage).mockImplementation(() =>
      (async function* () {
        yield { event: 'error' as const, data: { message: 'Provider exploded' } }
      })(),
    )
    typeInto(textbox, 'Ciao')

    fireEvent.click(screen.getByRole('button', { name: 'Invia' }))

    const alert = await screen.findByRole('alert')
    expect(alert).toHaveTextContent('Non è stato possibile ottenere una risposta')
    expect(alert).not.toHaveTextContent('Provider exploded')
    expect(textbox.value).toBe('Ciao')
  })

  it('sends Enter, forwards guide and mentions, and clears the composer on success', async () => {
    const { textbox } = await renderComposer()
    vi.mocked(streamAiMessage).mockImplementation(() =>
      (async function* () {
        yield* []
        yield {
          event: 'message.completed' as const,
          data: {
            assistant_message: {
              id: 2,
              role: 'assistant' as const,
              content: 'Risposta',
              mentions: [],
              attachments: [],
              model: null,
              input_tokens: null,
              output_tokens: null,
              created_at: '2026-11-02T10:00:01Z',
            },
            conversation: CONVERSATION,
          },
        }
      })(),
    )
    typeInto(textbox, 'Domanda')

    fireEvent.keyDown(textbox, { key: 'Enter' })

    await waitFor(() => expect(streamAiMessage).toHaveBeenCalledTimes(1))
    const [conversationId, input] = vi.mocked(streamAiMessage).mock.calls[0]
    expect(conversationId).toBe(7)
    expect(input).toMatchObject({
      content: 'Domanda',
      pageGuideKey: 'general',
      pageGuideText: 'GUIDE TEXT',
      locale: 'it',
      files: [],
      mentions: [],
    })
    await waitFor(() => expect(textbox.value).toBe(''))
  })

  it('does not send on Shift+Enter', async () => {
    const { textbox } = await renderComposer()
    typeInto(textbox, 'riga')

    fireEvent.keyDown(textbox, { key: 'Enter', shiftKey: true })

    expect(streamAiMessage).not.toHaveBeenCalled()
  })
})

describe('AiComposer without attachments (spec 0347 AC-029)', () => {
  it.each([
    ['no accepted type', { accepted_mimes: [] }],
    ['max_files 0', { max_files: 0 }],
  ])('hides the attach button and file input when %s', async (_label, limits) => {
    const base = buildAiConfig()
    await renderComposer(buildAiConfig({ limits: { ...base.limits, ...limits } }))

    expect(screen.queryByRole('button', { name: 'Allega file' })).toBeNull()
    expect(fileInput()).toBeNull()
    expect(screen.getByRole('button', { name: 'Menziona un record' })).toBeTruthy()
  })
})
