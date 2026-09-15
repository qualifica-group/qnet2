import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { RichTextContent } from '@/components/rich-text/rich-text-content'

const fetchAttachmentBinaryMock = vi.fn()
vi.mock('@/features/attachments/api', () => ({
  attachmentBinaryQueryKey: (id: number) => ['attachment-binary', id] as const,
  fetchAttachmentBinary: (...args: unknown[]) => fetchAttachmentBinaryMock(...args),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchAttachmentBinaryMock.mockReset()
  URL.createObjectURL = vi.fn(() => 'blob:mock-url')
  URL.revokeObjectURL = vi.fn()
})

describe('RichTextContent (spec 0128 AC-020)', () => {
  it('renders nothing for null html', () => {
    const { container } = render(<RichTextContent html={null} />, { wrapper: wrapper() })
    expect(container).toBeEmptyDOMElement()
  })

  it('renders the D-1 tag set as real DOM elements', () => {
    render(
      <RichTextContent html="<p><strong>Ciao</strong> <em>mondo</em></p><ul><li>uno</li></ul>" />,
      { wrapper: wrapper() },
    )
    expect(screen.getByText('Ciao').tagName).toBe('STRONG')
    expect(screen.getByText('mondo').tagName).toBe('EM')
    expect(screen.getByText('uno').closest('ul')).toBeInTheDocument()
  })

  it('fetches a saved image via fetchAttachmentBinary and shows the blob URL (AC-020)', async () => {
    fetchAttachmentBinaryMock.mockResolvedValue(new Blob(['x'], { type: 'image/png' }))

    const { container } = render(<RichTextContent html='<img data-attachment-id="5" alt="">' />, {
      wrapper: wrapper(),
    })

    await waitFor(() => {
      const img = container.querySelector('img')
      expect(img).toHaveAttribute('src', 'blob:mock-url')
    })
    expect(fetchAttachmentBinaryMock).toHaveBeenCalledWith(5, 'view')
  })

  it('shows an accessible placeholder when the attachment fetch fails (AC-020)', async () => {
    fetchAttachmentBinaryMock.mockRejectedValue(new Error('boom'))

    render(<RichTextContent html='<img data-attachment-id="9" alt="">' />, { wrapper: wrapper() })

    expect(await screen.findByRole('img', { name: /unavailable/i })).toBeInTheDocument()
  })

  it('never lets a <script> tag reach the DOM', () => {
    const { container } = render(
      <RichTextContent html='<p>hi<script>window.__pwned = true</script></p>' />,
      { wrapper: wrapper() },
    )
    expect(container.querySelector('script')).toBeNull()
  })

  it('renders a mention node through the provided mentionRenderer (D-7)', async () => {
    render(
      <RichTextContent
        html='<p><span data-type="mention" data-id="7" data-label="Anna Bianchi">@Anna Bianchi</span></p>'
        mentionRenderer={({ userId, label }) => <span data-mention-id={userId}>{label}</span>}
      />,
      { wrapper: wrapper() },
    )
    // Tiptap mounts node view portals after the initial commit (outside RTL's
    // synchronous `render()` flush), so the mention content needs a tick.
    const mentionEl = await screen.findByText('Anna Bianchi')
    expect(mentionEl).toHaveAttribute('data-mention-id', '7')
  })

  it('falls back to plain @label text without a mentionRenderer', async () => {
    render(
      <RichTextContent html='<p><span data-type="mention" data-id="7" data-label="Anna Bianchi">@Anna Bianchi</span></p>' />,
      { wrapper: wrapper() },
    )
    expect(await screen.findByText('@Anna Bianchi')).toBeInTheDocument()
  })
})
