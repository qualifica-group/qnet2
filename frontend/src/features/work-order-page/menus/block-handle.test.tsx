import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it } from 'vitest'
import type { Editor } from '@tiptap/react'
import i18n from '@/i18n'
import { MenusHarness } from '@/features/work-order-page/menus/menus-test-harness'
import { openMenuByName, selectText } from '@/features/work-order-page/menus/menus-test-utils'

/**
 * Spec 0380 AC-019 / AC-023: the block menu of the handle changes the document
 * (Transform into, Duplicate, Move up/down, Delete). The physical drag and the
 * hover placement need real layout: they are checked by hand in the browser.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

afterEach(() => {
  cleanup()
})

function setup(content: string, caretIn: string): Editor {
  let editor: Editor | null = null
  render(
    <MenusHarness
      content={content}
      onEditor={(current) => {
        editor = current
      }}
    />,
  )
  if (!editor) {
    throw new Error('editor not ready')
  }
  const ready: Editor = editor
  act(() => {
    selectText(ready, caretIn)
  })
  return ready
}

function blockTexts(editor: Editor): string[] {
  return editor.state.doc.children.map((node) => node.textContent)
}

async function openBlockMenu() {
  await screen.findByRole('button', { name: 'Block menu' })
  openMenuByName('Block menu')
  await screen.findByRole('menu')
}

describe('block handle menu (AC-019)', () => {
  it('is a labelled button next to the block holding the caret, reachable from the keyboard', async () => {
    setup('<p>One</p><p>Two</p>', 'Two')

    const handle = await screen.findByRole('button', { name: 'Block menu' })

    expect(handle).toHaveAttribute('aria-haspopup', 'menu')
    expect(handle.tagName).toBe('BUTTON')
    expect(handle).not.toHaveAttribute('tabindex', '-1')
  })

  it('Duplicate inserts a copy right after the block', async () => {
    const editor = setup('<p>One</p><p>Two</p>', 'One')

    await openBlockMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Duplicate' }))

    expect(blockTexts(editor)).toEqual(['One', 'One', 'Two'])
  })

  it('Move up swaps the block with the previous one', async () => {
    const editor = setup('<p>One</p><p>Two</p>', 'Two')

    await openBlockMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Move up' }))

    expect(blockTexts(editor)).toEqual(['Two', 'One'])
  })

  it('Move down swaps the block with the next one', async () => {
    const editor = setup('<p>One</p><p>Two</p>', 'One')

    await openBlockMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Move down' }))

    expect(blockTexts(editor)).toEqual(['Two', 'One'])
  })

  it('disables the moves that have nowhere to go', async () => {
    setup('<p>One</p><p>Two</p>', 'One')

    await openBlockMenu()

    expect(screen.getByRole('menuitem', { name: 'Move up' })).toHaveAttribute('aria-disabled', 'true')
    expect(screen.getByRole('menuitem', { name: 'Move down' })).not.toHaveAttribute('aria-disabled')
  })

  it('Delete removes the block', async () => {
    const editor = setup('<p>One</p><p>Two</p><p>Three</p>', 'Two')

    await openBlockMenu()
    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete' }))

    expect(blockTexts(editor)).toEqual(['One', 'Three'])
  })

  it('Turn into converts the block and checks the current type', async () => {
    const editor = setup('<p>One</p><p>Two</p>', 'Two')

    await openBlockMenu()
    fireEvent.keyDown(screen.getByRole('menuitem', { name: 'Turn into' }), { key: 'ArrowRight' })
    const paragraph = await screen.findByRole('menuitemcheckbox', { name: /^Paragraph/ })
    expect(paragraph).toHaveAttribute('aria-checked', 'true')

    fireEvent.click(screen.getByRole('menuitemcheckbox', { name: /^Heading 2/ }))

    await waitFor(() => expect(editor.getHTML()).toContain('<h2>Two</h2>'))
    expect(editor.getHTML()).toContain('<p>One</p>')
  })

  it('Turn into is disabled on a block that cannot be converted', async () => {
    setup('<table><tr><th>Head</th></tr></table><p>After</p>', 'Head')

    await openBlockMenu()

    expect(screen.getByRole('menuitem', { name: 'Turn into' })).toHaveAttribute('aria-disabled', 'true')
  })
})
