// @vitest-environment jsdom
import { afterEach, describe, expect, it, vi } from 'vitest'
import { Editor } from '@tiptap/core'
import { isCollapseToggle } from '@/features/work-order-page/editor/collapse-state'
import { createPageExtensions } from '@/features/work-order-page/editor/create-page-extensions'

const editors: Editor[] = []

function makeEditor(content = '<p></p>', onUpdate?: () => void): Editor {
  const editor = new Editor({ extensions: createPageExtensions(), content, ...(onUpdate ? { onUpdate } : {}) })
  editors.push(editor)
  return editor
}

/** Serializes with sorted attributes, so assertions do not depend on attribute order. */
function norm(html: string): string {
  const root = document.createElement('div')
  root.innerHTML = html
  root.querySelectorAll('*').forEach((element) => {
    const attrs = Array.from(element.attributes)
      .map((attr): [string, string] => [attr.name, attr.value])
      .sort(([a], [b]) => a.localeCompare(b))
    attrs.forEach(([name]) => element.removeAttribute(name))
    attrs.forEach(([name, value]) => element.setAttribute(name, value))
  })
  return root.innerHTML
}

function press(editor: Editor, init: KeyboardEventInit): void {
  editor.view.dom.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true, cancelable: true, ...init }))
}

function blockNames(editor: Editor): string[] {
  return editor.state.doc.children.map((node) => node.type.name)
}

function selectInside(editor: Editor, text: string): void {
  let found = -1
  editor.state.doc.descendants((node, pos) => {
    if (found < 0 && node.isText && node.text?.includes(text)) {
      found = pos + 1
    }
  })
  editor.commands.setTextSelection(found)
}

afterEach(() => {
  editors.splice(0).forEach((editor) => editor.destroy())
})

describe('page HTML profile round-trip (spec 0380 html_profile)', () => {
  it('keeps h1-h4, collapsible headings and hr', () => {
    const html = makeEditor(
      '<h1>a</h1><h2 data-collapsible="true">b</h2><h3>c</h3><h4>d</h4><hr>',
    ).getHTML()

    expect(html).toContain('<h1>a</h1>')
    expect(html).toContain('<h2 data-collapsible="true">b</h2>')
    expect(html).toContain('<h3>c</h3>')
    expect(html).toContain('<h4>d</h4>')
    expect(html).toContain('<hr>')
  })

  it('keeps tables with header cells, colspan and rowspan', () => {
    const html = makeEditor(
      '<table><thead><tr><th>h</th><th colspan="2">w</th></tr></thead><tbody><tr><td rowspan="2">x</td><td>y</td><td>z</td></tr></tbody></table>',
    ).getHTML()

    expect(html).toContain('<table><tbody><tr><th><p>h</p></th><th colspan="2"><p>w</p></th></tr>')
    expect(html).toContain('<td rowspan="2"><p>x</p></td>')
  })

  it.each(['info', 'success', 'warning', 'error'])('keeps the callout variant %s', (variant) => {
    const html = `<div data-type="callout" data-variant="${variant}"><p>t</p></div>`

    expect(norm(makeEditor(html).getHTML())).toBe(norm(html))
  })

  it('falls back to info for a callout variant outside the enum', () => {
    expect(makeEditor('<div data-type="callout" data-variant="x"><p>t</p></div>').getHTML()).toContain('data-variant="info"')
  })

  it('keeps the collapsible block structure', () => {
    const html =
      '<div data-type="collapsible"><div data-type="collapsible-summary">title</div><div data-type="collapsible-content"><p>body</p></div></div>'

    expect(norm(makeEditor(html).getHTML())).toBe(norm(html))
  })

  it('keeps date badges and drops an invalid datetime', () => {
    const editor = makeEditor('<p><time data-type="date" datetime="2026-11-11">x</time><time data-type="date" datetime="bad">y</time></p>')

    expect(editor.getHTML()).toContain('<time data-type="date" datetime="2026-11-11">')
    expect(editor.getHTML().match(/<time/g)).toHaveLength(1)
  })

  it('keeps highlight and text colors of the palette only', () => {
    const html = makeEditor(
      '<p><mark data-color="pink">a</mark><mark data-color="lime">b</mark><span data-color="red">c</span><span data-color="cyan">d</span></p>',
    ).getHTML()

    expect(html).toContain('<mark data-color="pink">a</mark>')
    expect(html).toContain('<span data-color="red">c</span>')
    expect(html).not.toContain('lime')
    expect(html).not.toContain('cyan')
  })

  it('keeps task lists with the checked state', () => {
    const editor = makeEditor(
      '<ul data-type="taskList"><li data-type="taskItem" data-checked="true"><p>a</p></li><li data-type="taskItem" data-checked="false"><p>b</p></li></ul>',
    )

    expect(editor.getHTML()).toContain('<ul data-type="taskList">')
    expect(editor.getHTML().match(/data-checked="true"/g)).toHaveLength(1)
    expect(editor.getHTML().match(/data-checked="false"/g)).toHaveLength(1)
  })

  it('keeps task-ref and poll blocks, dropping an id-less task-ref', () => {
    const editor = makeEditor(
      '<div data-type="task-ref" data-task-id="12"></div><div data-type="poll" data-poll-id="5"></div><div data-type="task-ref"></div>',
    )

    expect(norm(editor.getHTML())).toContain(norm('<div data-type="task-ref" data-task-id="12"></div>'))
    expect(norm(editor.getHTML())).toContain(norm('<div data-type="poll" data-poll-id="5"></div>'))
    expect(editor.getHTML().match(/task-ref/g)).toHaveLength(1)
  })
})

describe('table commands (AC-020)', () => {
  const cells = (editor: Editor, tag: string) => editor.view.dom.querySelectorAll(tag).length

  it('inserts a 3x3 table with a header row, adds and removes rows and columns, deletes it', () => {
    const editor = makeEditor()

    editor.commands.insertTable()
    expect(cells(editor, 'th')).toBe(3)
    expect(cells(editor, 'td')).toBe(6)

    editor.commands.addRowAfter()
    expect(cells(editor, 'tr')).toBe(4)
    // Move into the new body row (the header row is the one the caret started in).
    Array.from({ length: 3 }).forEach(() => editor.commands.goToNextCell())
    editor.commands.deleteRow()
    expect(cells(editor, 'tr')).toBe(3)

    editor.commands.addColumnAfter()
    expect(cells(editor, 'tr:first-child th')).toBe(4)
    editor.commands.deleteColumn()
    expect(cells(editor, 'tr:first-child th')).toBe(3)

    editor.commands.deleteTable()
    expect(cells(editor, 'table')).toBe(0)
  })

  it('moves to the next cell with Tab and to the previous with Shift-Tab', () => {
    const editor = makeEditor('<table><tr><th>one</th><th>two</th></tr><tr><td>three</td><td>four</td></tr></table>')
    selectInside(editor, 'one')

    const selectedText = () => editor.state.doc.textBetween(editor.state.selection.from, editor.state.selection.to)
    press(editor, { key: 'Tab', code: 'Tab', keyCode: 9 })
    expect(selectedText()).toBe('two')
    press(editor, { key: 'Tab', code: 'Tab', keyCode: 9 })
    expect(selectedText()).toBe('three')
    press(editor, { key: 'Tab', code: 'Tab', keyCode: 9, shiftKey: true })
    expect(selectedText()).toBe('two')
  })
})

describe('keyboard shortcuts (AC-018, D-9)', () => {
  it.each([
    [1, '1', 49, 'heading'],
    [4, '4', 52, 'heading'],
    [0, '0', 48, 'paragraph'],
  ])('Ctrl+Alt+%i sets %s', (level, key, keyCode, expected) => {
    const editor = makeEditor('<h2>t</h2>')
    selectInside(editor, 't')

    press(editor, { key, code: `Digit${key}`, keyCode, ctrlKey: true, altKey: true })

    expect(blockNames(editor)[0]).toBe(expected)
    if (expected === 'heading') {
      expect(editor.state.doc.firstChild?.attrs.level).toBe(level)
    }
  })

  it.each([
    ['8', 56, 'bulletList'],
    ['7', 55, 'orderedList'],
    ['9', 57, 'taskList'],
  ])('Ctrl+Shift+%s makes a %s', (key, keyCode, expected) => {
    const editor = makeEditor('<p>t</p>')
    selectInside(editor, 't')

    press(editor, { key, code: `Digit${key}`, keyCode, ctrlKey: true, shiftKey: true })

    expect(blockNames(editor)[0]).toBe(expected)
  })

  it('Alt+Shift+Arrow swaps the block with its neighbour', () => {
    const editor = makeEditor('<p>first</p><p>second</p><p>third</p>')
    selectInside(editor, 'second')

    press(editor, { key: 'ArrowUp', code: 'ArrowUp', keyCode: 38, altKey: true, shiftKey: true })
    expect(editor.getText({ blockSeparator: '|' })).toBe('second|first|third')

    press(editor, { key: 'ArrowDown', code: 'ArrowDown', keyCode: 40, altKey: true, shiftKey: true })
    press(editor, { key: 'ArrowDown', code: 'ArrowDown', keyCode: 40, altKey: true, shiftKey: true })
    expect(editor.getText({ blockSeparator: '|' })).toBe('first|third|second')
  })
})

describe('block commands (AC-019)', () => {
  it('moves, duplicates and deletes a block, also by explicit position', () => {
    const editor = makeEditor('<p>a</p><p>b</p>')
    selectInside(editor, 'a')

    expect(editor.commands.moveBlockDown()).toBe(true)
    expect(editor.getText({ blockSeparator: '|' })).toBe('b|a')
    expect(editor.commands.moveBlockDown()).toBe(false)

    editor.commands.duplicateBlock()
    expect(editor.getText({ blockSeparator: '|' })).toBe('b|a|a')

    editor.commands.deleteBlock(0)
    expect(editor.getText({ blockSeparator: '|' })).toBe('a|a')
  })

  it('refuses to duplicate a poll', () => {
    const editor = makeEditor('<div data-type="poll" data-poll-id="5"></div><p>x</p>')

    expect(editor.commands.duplicateBlock(0)).toBe(false)
  })

  it('transforms a block into every supported type', () => {
    const editor = makeEditor('<p>t</p>')
    selectInside(editor, 't')

    editor.commands.setBlockType('heading3')
    expect(editor.getHTML()).toMatch(/^<h3>t<\/h3>/)
    editor.commands.setBlockType('bulletList')
    expect(blockNames(editor)[0]).toBe('bulletList')
    editor.commands.setBlockType('callout')
    expect(blockNames(editor)[0]).toBe('callout')
    editor.commands.setBlockType('paragraph')
    expect(editor.getHTML()).toMatch(/^<p>t<\/p>/)
  })

  it('leaves non transformable blocks alone', () => {
    const editor = makeEditor('<div data-type="poll" data-poll-id="5"></div>')

    expect(editor.commands.setBlockType('paragraph', 0)).toBe(false)
  })
})

describe('collapsing (AC-021, D-8)', () => {
  const hidden = (editor: Editor) => editor.view.dom.querySelectorAll('.page-collapsed-hidden').length

  it('closing a heading hides the blocks up to the next heading of the same or higher level', () => {
    const onUpdate = vi.fn()
    const editor = makeEditor(
      '<h2 data-collapsible="true">a</h2><p>1</p><h3>sub</h3><p>2</p><h2>b</h2><p>3</p>',
      onUpdate,
    )
    expect(hidden(editor)).toBe(0)

    editor.commands.toggleCollapsed(0)
    expect(hidden(editor)).toBe(3)
    expect(editor.view.dom.querySelector('.is-collapsed')?.textContent).toContain('a')

    editor.commands.toggleCollapsed(0)
    expect(hidden(editor)).toBe(0)
    expect(onUpdate).not.toHaveBeenCalled()
  })

  it('closing a collapsible block hides its body, through the toggle button, with no document change', () => {
    const onUpdate = vi.fn()
    const editor = makeEditor(
      '<div data-type="collapsible"><div data-type="collapsible-summary">title</div><div data-type="collapsible-content"><p>body</p></div></div><p>after</p>',
      onUpdate,
    )
    const before = norm(editor.getHTML())

    editor.view.dom.querySelector<HTMLButtonElement>('button.page-collapse-toggle')?.click()

    expect(hidden(editor)).toBe(1)
    expect(norm(editor.getHTML())).toBe(before)
    expect(onUpdate).not.toHaveBeenCalled()
  })
})

describe('collapse toggle as the last block', () => {
  it('is recognised as a view-only update even when the editor appends a trailing paragraph', () => {
    const toggles: boolean[] = []
    const editor = new Editor({
      extensions: createPageExtensions(),
      content: '<h2 data-collapsible="true">a</h2><div data-type="poll" data-poll-id="5"></div>',
      onUpdate: ({ transaction }) => toggles.push(isCollapseToggle(transaction)),
    })
    editors.push(editor)

    editor.commands.toggleCollapsed(0)

    expect(toggles).toEqual([true])
  })
})

describe('inline commands (AC-016 marks)', () => {
  it('applies and removes highlight and text color, and inserts a date', () => {
    const editor = makeEditor('<p>text</p>')
    editor.commands.selectAll()

    editor.commands.setHighlight('green')
    editor.commands.setTextColor('blue')
    expect(editor.getHTML()).toContain('<mark data-color="green">')
    expect(editor.getHTML()).toContain('<span data-color="blue">')

    editor.commands.unsetHighlight()
    editor.commands.unsetTextColor()
    expect(editor.getHTML()).toBe('<p>text</p>')

    editor.commands.insertDate('2026-11-11')
    expect(editor.getHTML()).toContain('datetime="2026-11-11"')
  })

  it('sets a callout variant and a collapsible heading, inserts a collapsible block', () => {
    const editor = makeEditor('<p>t</p>')
    selectInside(editor, 't')

    editor.commands.setHeading({ level: 2 })
    editor.commands.toggleHeadingCollapsible()
    expect(editor.getHTML()).toMatch(/^<h2 data-collapsible="true">t<\/h2>/)

    editor.commands.setBlockType('callout')
    editor.commands.setCallout('warning')
    expect(editor.getHTML()).toContain('data-variant="warning"')

    editor.commands.insertCollapsible()
    expect(editor.getHTML()).toContain('data-type="collapsible"')
  })
})
