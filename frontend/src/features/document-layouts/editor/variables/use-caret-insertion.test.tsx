import { useRef, useState } from 'react'
import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useCaretInsertion } from '@/features/document-layouts/editor/variables/use-caret-insertion'

function Harness({ initialValue }: { initialValue: string }) {
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const [value, setValue] = useState(initialValue)
  const { trackSelection, insertAtCaret } = useCaretInsertion(textareaRef, value, setValue)

  return (
    <div>
      <textarea
        ref={textareaRef}
        value={value}
        onChange={(event) => setValue(event.target.value)}
        onSelect={trackSelection}
        aria-label="run text"
      />
      <button type="button" onClick={() => insertAtCaret('{quote.code}')}>
        Insert
      </button>
    </div>
  )
}

/** Spec 0069 AC-122: the token lands at the tracked caret, not appended to the end of the text. */
describe('useCaretInsertion (AC-122)', () => {
  it('inserts the token at the caret position when the cursor is in the middle of the text', () => {
    render(<Harness initialValue="Hello world" />)
    const textarea = screen.getByLabelText('run text') as HTMLTextAreaElement

    textarea.focus()
    textarea.setSelectionRange(5, 5)
    fireEvent.select(textarea)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect(textarea.value).toBe('Hello{quote.code} world')
  })

  it('replaces a selected range instead of inserting alongside it', () => {
    render(<Harness initialValue="Hello world" />)
    const textarea = screen.getByLabelText('run text') as HTMLTextAreaElement

    textarea.focus()
    textarea.setSelectionRange(0, 5) // select "Hello"
    fireEvent.select(textarea)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect(textarea.value).toBe('{quote.code} world')
  })

  it('falls back to the end of the text when nothing was ever selected', () => {
    render(<Harness initialValue="Hello" />)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect((screen.getByLabelText('run text') as HTMLTextAreaElement).value).toBe('Hello{quote.code}')
  })
})
