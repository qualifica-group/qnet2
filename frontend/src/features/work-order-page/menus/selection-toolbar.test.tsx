import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it } from 'vitest'
import { Editor } from '@tiptap/core'
import i18n from '@/i18n'
import { createPageExtensions } from '@/features/work-order-page/editor/create-page-extensions'
import { openMenuByName, selectText } from '@/features/work-order-page/menus/menus-test-utils'
import { SelectionToolbar } from '@/features/work-order-page/menus/selection-toolbar'

/**
 * Spec 0420 AC-016: the selection bar applies the expected mark/block with each
 * command and checks the active style. The floating positioning (BubbleMenu) is
 * floating-ui's job and is verified in the browser; here the bar content is
 * rendered directly.
 */

const editors: Editor[] = []

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

afterEach(() => {
  cleanup()
  editors.splice(0).forEach((editor) => editor.destroy())
})

function setup(content = '<p>Hello brave world</p>') {
  const element = document.createElement('div')
  document.body.appendChild(element)
  const editor = new Editor({ element, extensions: createPageExtensions(), content })
  editors.push(editor)
  render(<SelectionToolbar editor={editor} />)
  act(() => {
    selectText(editor, 'brave')
  })
  return editor
}

describe('SelectionToolbar (AC-016)', () => {
  it.each([
    ['Bold', '<strong>brave</strong>'],
    ['Italic', '<em>brave</em>'],
    ['Underline', '<u>brave</u>'],
    ['Strikethrough', '<s>brave</s>'],
  ])('%s applies its mark and shows it pressed', (name, html) => {
    const editor = setup()

    fireEvent.click(screen.getByRole('button', { name }))

    expect(editor.getHTML()).toContain(html)
    expect(screen.getByRole('button', { name })).toHaveAttribute('aria-pressed', 'true')
  })

  it('shows the Teams-style letters for bold, italic, underline', () => {
    setup()

    expect(screen.getByRole('button', { name: 'Bold' })).toHaveTextContent('B')
    expect(screen.getByRole('button', { name: 'Italic' })).toHaveTextContent('I')
    expect(screen.getByRole('button', { name: 'Underline' })).toHaveTextContent('U')
  })

  it('applies a highlight color and removes it', () => {
    const editor = setup()

    openMenuByName('Highlighter')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Green' }))
    expect(editor.getHTML()).toMatch(/<mark[^>]*data-color="green"[^>]*>brave<\/mark>/)

    openMenuByName('Highlighter')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Remove highlight' }))
    expect(editor.getHTML()).not.toContain('<mark')
  })

  it('applies a text color and removes it', () => {
    const editor = setup()

    openMenuByName('Text color')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Red' }))
    expect(editor.getHTML()).toMatch(/data-color="red"[^>]*>brave</)

    openMenuByName('Text color')
    fireEvent.click(screen.getByRole('menuitem', { name: 'Remove color' }))
    expect(editor.getHTML()).not.toContain('data-color')
  })

  it('rejects an unsafe link with an accessible error and accepts an https one', async () => {
    const editor = setup()

    fireEvent.click(screen.getByRole('button', { name: 'Link' }))
    const input = screen.getByLabelText('Link address')

    fireEvent.change(input, { target: { value: 'javascript:alert(1)' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))
    const alert = await screen.findByRole('alert')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    expect(input).toHaveAttribute('aria-describedby', alert.id)
    expect(editor.getHTML()).not.toContain('<a')

    fireEvent.change(input, { target: { value: 'https://example.com' } })
    fireEvent.click(screen.getByRole('button', { name: 'Apply' }))
    expect(editor.getHTML()).toMatch(/<a [^>]*href="https:\/\/example\.com"[^>]*>brave<\/a>/)
    expect(screen.getByRole('button', { name: 'Link' })).toBeInTheDocument()
  })

  it('lists the styles with shortcuts and checks the active one', async () => {
    const editor = setup()

    openMenuByName('Styles')
    const paragraph = screen.getByRole('menuitemcheckbox', { name: /^Paragraph/ })
    expect(paragraph).toHaveAttribute('aria-checked', 'true')
    expect(paragraph).toHaveTextContent('Ctrl+Alt+0')
    expect(screen.getByRole('menuitemcheckbox', { name: /^Collapsible heading 4/ })).toBeInTheDocument()
    expect(screen.getByRole('menuitemcheckbox', { name: /^Inline code/ })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: /^Heading 2/ }))
    expect(editor.getHTML()).toContain('<h2>')
    await waitFor(() => expect(screen.queryByRole('menu')).not.toBeInTheDocument())

    openMenuByName('Styles')
    await waitFor(() =>
      expect(screen.getByRole('menuitemcheckbox', { name: /^Heading 2/ })).toHaveAttribute('aria-checked', 'true'),
    )
    expect(screen.getByRole('menuitemcheckbox', { name: /^Paragraph/ })).toHaveAttribute('aria-checked', 'false')
  })

  it.each([
    [/^Bulleted list/, '<ul>'],
    [/^Numbered list/, '<ol>'],
    [/^Checklist/, 'data-type="taskList"'],
    [/^Quote/, '<blockquote>'],
    [/^Callout/, 'data-type="callout"'],
    [/^Collapsible heading 3/, 'data-collapsible="true"'],
    [/^Inline code/, '<code>brave</code>'],
  ])('style %s produces %s', (name, html) => {
    const editor = setup()

    openMenuByName('Styles')
    fireEvent.click(screen.getByRole('menuitemcheckbox', { name }))

    expect(editor.getHTML()).toContain(html)
  })
})
