import { act, cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeAll, describe, expect, it, vi } from 'vitest'
import type { Editor } from '@tiptap/react'
import i18n from '@/i18n'
import { MenusHarness } from '@/features/work-order-page/menus/menus-test-harness'

/** Spec 0380 AC-017: "/" opens the filterable block menu; Enter inserts the block and removes "/query". */

const onInsertTask = vi.fn()
const onInsertPoll = vi.fn()

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

afterEach(() => {
  cleanup()
  onInsertTask.mockReset()
  onInsertPoll.mockReset()
})

function setup(content = '<p></p>'): Editor {
  let editor: Editor | null = null
  render(
    <MenusHarness
      content={content}
      onEditor={(current) => {
        editor = current
      }}
      onInsertTask={onInsertTask}
      onInsertPoll={onInsertPoll}
    />,
  )
  if (!editor) {
    throw new Error('editor not ready')
  }
  return editor
}

function type(editor: Editor, text: string) {
  act(() => {
    editor.chain().focus().insertContent(text).run()
  })
}

function press(editor: Editor, key: string) {
  act(() => {
    fireEvent.keyDown(editor.view.dom, { key })
  })
}

describe('slash menu (AC-017)', () => {
  it('opens on "/" at the start of a block with every block and insertion', async () => {
    const editor = setup()

    type(editor, '/')

    const options = await screen.findAllByRole('option')
    const names = options.map((option) => option.textContent ?? '')
    for (const label of ['Paragraph', 'Heading 1', 'Collapsible heading 4', 'Bulleted list', 'Table', 'Divider', 'Date', 'Image', 'Task', 'Poll']) {
      expect(names.some((name) => name.startsWith(label))).toBe(true)
    }
    expect(screen.getByRole('listbox', { name: 'Insert a block' })).toBeInTheDocument()
  })

  it('also opens after a space, but not inside a word', async () => {
    const editor = setup('<p>word</p>')
    editor.commands.focus('end')

    type(editor, '/')
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()

    type(editor, ' /')
    expect(await screen.findByRole('listbox')).toBeInTheDocument()
  })

  it('filters by the typed text', async () => {
    const editor = setup()

    type(editor, '/tab')

    await waitFor(() => expect(screen.getAllByRole('option')).toHaveLength(1))
    expect(screen.getByRole('option', { name: /Table/ })).toBeInTheDocument()
  })

  it('shows an empty state when nothing matches', async () => {
    const editor = setup()

    type(editor, '/zzzz')

    expect(await screen.findByText('No results')).toBeInTheDocument()
  })

  it('inserts the highlighted block on Enter and removes the typed "/query"', async () => {
    const editor = setup()

    type(editor, '/tab')
    await screen.findByRole('option', { name: /Table/ })
    press(editor, 'Enter')

    await waitFor(() => expect(editor.getHTML()).toContain('<table'))
    expect(editor.getText()).not.toContain('/tab')
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('moves the highlight with the arrow keys', async () => {
    const editor = setup()

    type(editor, '/heading')
    await screen.findByRole('option', { name: /^Heading 1/ })
    press(editor, 'ArrowDown')
    await waitFor(() => expect(screen.getByRole('option', { name: /^Heading 2/ })).toHaveAttribute('aria-selected', 'true'))
    press(editor, 'Enter')

    await waitFor(() => expect(editor.getHTML()).toContain('<h2>'))
    expect(editor.getText()).not.toContain('/heading')
  })

  it('closes on Escape without inserting', async () => {
    const editor = setup()

    type(editor, '/tab')
    await screen.findByRole('listbox')
    press(editor, 'Escape')

    await waitFor(() => expect(screen.queryByRole('listbox')).not.toBeInTheDocument())
    expect(editor.getHTML()).not.toContain('<table')
  })

  it('inserts with a click on an option', async () => {
    const editor = setup()

    type(editor, '/bullet')
    fireEvent.mouseDown(await screen.findByRole('option', { name: /Bulleted list/ }))

    await waitFor(() => expect(editor.getHTML()).toContain('<ul>'))
  })

  it('hands Task and Poll to the page dialogs', async () => {
    const editor = setup()

    type(editor, '/task')
    await screen.findByRole('option', { name: /^Task/ })
    press(editor, 'Enter')
    await waitFor(() => expect(onInsertTask).toHaveBeenCalledTimes(1))
    expect(editor.getText()).not.toContain('/task')

    type(editor, '/poll')
    await screen.findByRole('option', { name: /^Poll/ })
    press(editor, 'Enter')
    await waitFor(() => expect(onInsertPoll).toHaveBeenCalledTimes(1))
  })
})
