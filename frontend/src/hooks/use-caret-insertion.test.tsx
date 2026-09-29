import { useRef, useState } from 'react'
import type { RefObject } from 'react'
import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { useCaretInsertion } from '@/hooks/use-caret-insertion'

type FieldElement = HTMLInputElement | HTMLTextAreaElement

interface HarnessProps {
  initialValue: string
  element: 'input' | 'textarea'
}

/**
 * Exercises the hook against BOTH element kinds it is generic over: an
 * `<input>` (`email-templates`'s subject field) and a `<textarea>`
 * (`document-layouts`'s run text, migrated here from that module's own
 * former copy, spec 0069 AC-122).
 */
function Harness({ initialValue, element }: HarnessProps) {
  const elementRef = useRef<FieldElement>(null)
  const [value, setValue] = useState(initialValue)
  const { trackSelection, insertAtCaret } = useCaretInsertion(elementRef, value, setValue)

  return (
    <div>
      {element === 'input' ? (
        <input
          ref={elementRef as RefObject<HTMLInputElement | null>}
          value={value}
          onChange={(event) => setValue(event.target.value)}
          onSelect={trackSelection}
          aria-label="run text"
        />
      ) : (
        <textarea
          ref={elementRef as RefObject<HTMLTextAreaElement | null>}
          value={value}
          onChange={(event) => setValue(event.target.value)}
          onSelect={trackSelection}
          aria-label="run text"
        />
      )}
      <button type="button" onClick={() => insertAtCaret('{quote.code}')}>
        Insert
      </button>
    </div>
  )
}

describe.each([['input' as const], ['textarea' as const]])('useCaretInsertion — %s element', (element) => {
  it('inserts the token at the caret position when the cursor is in the middle of the text', () => {
    render(<Harness initialValue="Hello world" element={element} />)
    const field = screen.getByLabelText('run text') as FieldElement

    field.focus()
    field.setSelectionRange(5, 5)
    fireEvent.select(field)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect(field.value).toBe('Hello{quote.code} world')
  })

  it('replaces a selected range instead of inserting alongside it', () => {
    render(<Harness initialValue="Hello world" element={element} />)
    const field = screen.getByLabelText('run text') as FieldElement

    field.focus()
    field.setSelectionRange(0, 5) // select "Hello"
    fireEvent.select(field)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect(field.value).toBe('{quote.code} world')
  })

  it('falls back to the end of the text when nothing was ever selected', () => {
    render(<Harness initialValue="Hello" element={element} />)

    fireEvent.click(screen.getByRole('button', { name: 'Insert' }))

    expect((screen.getByLabelText('run text') as FieldElement).value).toBe('Hello{quote.code}')
  })
})
