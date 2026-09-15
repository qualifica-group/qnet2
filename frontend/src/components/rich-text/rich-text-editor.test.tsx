import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { Node, mergeAttributes } from '@tiptap/core'
import i18n from '@/i18n'
import { RichTextEditor } from '@/components/rich-text/rich-text-editor'
import { RICH_TEXT_PROSE_CLASS } from '@/components/rich-text/rich-text-prose'

// jsdom has no layout engine: ProseMirror's view queries these when syncing
// its selection/decorations to the DOM. Stubbed here (never in
// `src/test/setup.ts`, config-protection hook) rather than mocked away,
// so the editor exercises its real command pipeline end to end.
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

const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({ toast: { error: (...args: unknown[]) => toastErrorMock(...args) } }))

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  toastErrorMock.mockReset()
})

/** A saved image's node view queries via TanStack Query even when disabled (AC-020's `enabled` gate still needs a provider). */
function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

/** Selects the whole document via ProseMirror's own `Mod-a` keymap (baseKeymap), so a mark toggle has text to wrap. */
function selectAll(editorDom: Element) {
  fireEvent.keyDown(editorDom, { key: 'a', code: 'KeyA', ctrlKey: true })
}

function pngFile(name = 'photo.png', sizeBytes?: number): File {
  const file = new File([new Uint8Array([1, 2, 3, 4])], name, { type: 'image/png' })
  if (sizeBytes !== undefined) {
    Object.defineProperty(file, 'size', { value: sizeBytes })
  }
  return file
}

function pasteFiles(editorDom: Element, files: File[]) {
  const event = new Event('paste', { bubbles: true, cancelable: true })
  Object.defineProperty(event, 'clipboardData', {
    // `getData` is read by the Link extension's own paste handling
    // (auto-link detection on `text/html`) before our own listener runs.
    value: { files, getData: () => '' },
  })
  editorDom.dispatchEvent(event)
}

describe('RichTextEditor (spec 0128 AC-017/AC-018/AC-019)', () => {
  it('renders a toolbar button per documented control, with aria-label and aria-pressed', () => {
    render(<RichTextEditor value={null} onChange={vi.fn()} />, { wrapper: wrapper() })

    for (const name of [
      'Bold',
      'Italic',
      'Underline',
      'Strikethrough',
      'Bullet list',
      'Numbered list',
      'Heading 2',
      'Heading 3',
      'Quote',
      'Code block',
      'Link',
    ]) {
      const button = screen.getByRole('button', { name })
      expect(button).toHaveAttribute('aria-pressed', 'false')
    }
    expect(screen.getByRole('button', { name: 'Image' })).toBeInTheDocument()
  })

  it('toggles bold/italic/underline/strike and emits the matching tag (AC-017)', async () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value="<p>hello</p>" onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement
    selectAll(editorDom)

    fireEvent.click(screen.getByRole('button', { name: 'Bold' }))
    expect(screen.getByRole('button', { name: 'Bold' })).toHaveAttribute('aria-pressed', 'true')
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<strong>'))

    fireEvent.click(screen.getByRole('button', { name: 'Italic' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<em>'))

    fireEvent.click(screen.getByRole('button', { name: 'Underline' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<u>'))

    fireEvent.click(screen.getByRole('button', { name: 'Strikethrough' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<s>'))
  })

  it('toggles block-level tags: lists, headings, quote, code block (AC-017)', () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value="<p>hello</p>" onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement
    selectAll(editorDom)

    fireEvent.click(screen.getByRole('button', { name: 'Bullet list' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<ul>'))

    fireEvent.click(screen.getByRole('button', { name: 'Bullet list' })) // untoggle back to paragraph
    fireEvent.click(screen.getByRole('button', { name: 'Numbered list' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<ol>'))

    fireEvent.click(screen.getByRole('button', { name: 'Numbered list' }))
    fireEvent.click(screen.getByRole('button', { name: 'Heading 2' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<h2>'))

    fireEvent.click(screen.getByRole('button', { name: 'Heading 2' }))
    fireEvent.click(screen.getByRole('button', { name: 'Heading 3' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<h3>'))

    fireEvent.click(screen.getByRole('button', { name: 'Heading 3' }))
    fireEvent.click(screen.getByRole('button', { name: 'Quote' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<blockquote>'))

    fireEvent.click(screen.getByRole('button', { name: 'Quote' }))
    fireEvent.click(screen.getByRole('button', { name: 'Code block' }))
    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('<pre>'))
  })

  it('syncs an external value change (e.g. form reset) without recreating the editor', () => {
    const onChange = vi.fn()
    const { rerender, container } = render(<RichTextEditor value={null} onChange={onChange} />, {
      wrapper: wrapper(),
    })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement
    expect(editorDom.textContent).toBe('')

    rerender(<RichTextEditor value="<p>hello</p>" onChange={onChange} />)
    expect(editorDom.textContent).toBe('hello')
  })

  it('inserts a pasted PNG as a data: image (AC-018)', async () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value={null} onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement

    pasteFiles(editorDom, [pngFile()])

    await waitFor(() =>
      expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('data:image/png')),
    )
    expect(toastErrorMock).not.toHaveBeenCalled()
  })

  it('rejects a pasted file over the size limit with a toast and no insert (AC-018)', () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value={null} onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement

    pasteFiles(editorDom, [pngFile('huge.png', 6 * 1024 * 1024)])

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(onChange).not.toHaveBeenCalledWith(expect.stringContaining('data:image/png'))
  })

  it('rejects a pasted non-image file with a toast and no insert (AC-018)', () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value={null} onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement
    const textFile = new File(['hello'], 'notes.txt', { type: 'text/plain' })

    pasteFiles(editorDom, [textFile])

    expect(toastErrorMock).toHaveBeenCalledTimes(1)
    expect(editorDom.textContent).toBe('')
    expect(onChange).not.toHaveBeenCalledWith(expect.stringContaining('data:'))
  })

  it('rejects a javascript: link and applies an https: link (AC-019)', () => {
    const onChange = vi.fn()
    const { container } = render(<RichTextEditor value="<p>hello</p>" onChange={onChange} />, { wrapper: wrapper() })
    const editorDom = container.querySelector('.ProseMirror') as HTMLElement
    selectAll(editorDom)

    fireEvent.click(screen.getByRole('button', { name: 'Link' }))
    const urlInput = screen.getByLabelText('URL')
    fireEvent.change(urlInput, { target: { value: 'javascript:alert(1)' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(screen.getByText(/Invalid URL/i)).toBeInTheDocument()
    expect(onChange).not.toHaveBeenCalledWith(expect.stringContaining('<a '))

    fireEvent.change(urlInput, { target: { value: 'https://example.com' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))

    expect(onChange).toHaveBeenLastCalledWith(expect.stringContaining('href="https://example.com"'))
  })

  it('highlights a mention node with accent tokens, no class/style on the node itself (D-7 follow-up)', () => {
    // Minimal stand-in for F2's real `Mention` extension: same wire format
    // (`span[data-type="mention"][data-id][data-label]`), just enough to
    // prove the shared prose class actually targets it once injected.
    const TestMention = Node.create({
      name: 'mention',
      group: 'inline',
      inline: true,
      atom: true,
      addAttributes: () => ({ id: { default: null }, label: { default: null } }),
      parseHTML: () => [{ tag: 'span[data-type="mention"]' }],
      renderHTML: ({ node }) => [
        'span',
        mergeAttributes({ 'data-type': 'mention', 'data-id': node.attrs.id, 'data-label': node.attrs.label }),
        `@${node.attrs.label}`,
      ],
    })

    const { container } = render(
      <RichTextEditor
        value='<p><span data-type="mention" data-id="7" data-label="Anna">@Anna</span></p>'
        onChange={vi.fn()}
        extraExtensions={[TestMention]}
      />,
      { wrapper: wrapper() },
    )

    const mentionNode = container.querySelector('[data-type="mention"]')
    expect(mentionNode).not.toBeNull()
    expect(mentionNode).not.toHaveAttribute('class')
    expect(mentionNode).not.toHaveAttribute('style')
    // The tint comes from the shared prose class on the ancestor, never from
    // an attribute the D-1 sanitizer would strip on the node itself.
    expect(RICH_TEXT_PROSE_CLASS).toContain('[&_[data-type="mention"]]:bg-accent')
    expect(RICH_TEXT_PROSE_CLASS).toContain('[&_[data-type="mention"]]:text-accent-foreground')
  })
})
