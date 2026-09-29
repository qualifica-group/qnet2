import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { EmailRecipientsInput, type EmailRecipientSuggestion } from '@/components/ui/email-recipients-input'

const suggestions: EmailRecipientSuggestion[] = [
  { email: 'anna@example.com', label: 'Anna Bianchi', source: 'registry' },
  { email: 'marco@example.com', label: 'Marco Rossi', source: 'referent' },
]

function renderInput(props: Partial<Parameters<typeof EmailRecipientsInput>[0]> = {}) {
  const onChange = vi.fn()
  render(
    <EmailRecipientsInput
      value={[]}
      onChange={onChange}
      removeLabel="Remove"
      placeholder="Add a recipient…"
      {...props}
    />,
  )
  return { onChange }
}

describe('EmailRecipientsInput', () => {
  it('adds a valid address on Enter and clears the field', () => {
    const { onChange } = renderInput()
    const input = screen.getByRole('combobox')

    fireEvent.change(input, { target: { value: 'anna@example.com' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).toHaveBeenCalledWith(['anna@example.com'])
  })

  it('adds a valid address as soon as a trailing comma is typed', () => {
    const { onChange } = renderInput()
    const input = screen.getByRole('combobox')

    fireEvent.change(input, { target: { value: 'anna@example.com,' } })

    expect(onChange).toHaveBeenCalledWith(['anna@example.com'])
    expect(input).toHaveValue('')
  })

  it('rejects an invalid address: does not add it, reports it and marks the field invalid', () => {
    const onInvalidInput = vi.fn()
    const { onChange } = renderInput({ onInvalidInput })
    const input = screen.getByRole('combobox')

    fireEvent.change(input, { target: { value: 'not-an-email' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).not.toHaveBeenCalled()
    expect(onInvalidInput).toHaveBeenCalledWith('not-an-email')
    expect(input).toHaveAttribute('aria-invalid', 'true')
    // Kept in the field so the user can fix it, not silently dropped.
    expect(input).toHaveValue('not-an-email')
  })

  it('clears the invalid state as soon as the user edits the field again', () => {
    renderInput({ onInvalidInput: vi.fn() })
    const input = screen.getByRole('combobox')
    fireEvent.change(input, { target: { value: 'not-an-email' } })
    fireEvent.keyDown(input, { key: 'Enter' })
    expect(input).toHaveAttribute('aria-invalid', 'true')

    fireEvent.change(input, { target: { value: 'not-an-email-2' } })

    expect(input).toHaveAttribute('aria-invalid', 'false')
  })

  it('splits a multi-address paste on commas/semicolons/whitespace and adds every valid one', () => {
    const onInvalidInput = vi.fn()
    const { onChange } = renderInput({ onInvalidInput })
    const input = screen.getByRole('combobox')

    fireEvent.paste(input, {
      clipboardData: { getData: () => 'anna@example.com, marco@example.com;bad-one x@y.com' },
    })

    expect(onChange).toHaveBeenLastCalledWith(['anna@example.com', 'marco@example.com', 'x@y.com'])
    expect(onInvalidInput).toHaveBeenCalledWith('bad-one')
  })

  it('shows matching suggestions and selects one via keyboard (ArrowDown + Enter)', () => {
    const { onChange } = renderInput({ suggestions })
    const input = screen.getByRole('combobox')

    fireEvent.focus(input)
    fireEvent.change(input, { target: { value: 'an' } })
    expect(screen.getByRole('option', { name: /Anna Bianchi/ })).toBeInTheDocument()
    expect(screen.queryByRole('option', { name: /Marco Rossi/ })).not.toBeInTheDocument()

    fireEvent.keyDown(input, { key: 'ArrowDown' })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).toHaveBeenCalledWith(['anna@example.com'])
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('selecting a suggestion by click does not blur-commit the leftover input text', () => {
    const { onChange } = renderInput({ suggestions })
    const input = screen.getByRole('combobox')

    fireEvent.focus(input)
    fireEvent.change(input, { target: { value: 'marco' } })
    fireEvent.click(screen.getByRole('option', { name: /Marco Rossi/ }))

    expect(onChange).toHaveBeenCalledWith(['marco@example.com'])
  })

  it('excludes already-selected addresses from the suggestion pool', () => {
    renderInput({ value: ['anna@example.com'], suggestions })
    const input = screen.getByRole('combobox')

    fireEvent.focus(input)

    expect(screen.queryByRole('option', { name: /Anna Bianchi/ })).not.toBeInTheDocument()
    expect(screen.getByRole('option', { name: /Marco Rossi/ })).toBeInTheDocument()
  })

  it('renders a chip per address and removes it via its remove button', () => {
    const { onChange } = renderInput({ value: ['anna@example.com', 'marco@example.com'] })

    fireEvent.click(screen.getByRole('button', { name: 'Remove anna@example.com' }))

    expect(onChange).toHaveBeenCalledWith(['marco@example.com'])
  })

  it('Backspace on an empty field removes the last chip', () => {
    const { onChange } = renderInput({ value: ['anna@example.com', 'marco@example.com'] })
    const input = screen.getByRole('combobox')

    fireEvent.keyDown(input, { key: 'Backspace' })

    expect(onChange).toHaveBeenCalledWith(['anna@example.com'])
  })

  it('Backspace with text still in the field does not remove a chip', () => {
    const { onChange } = renderInput({ value: ['anna@example.com'] })
    const input = screen.getByRole('combobox')
    fireEvent.change(input, { target: { value: 'x' } })

    fireEvent.keyDown(input, { key: 'Backspace' })

    expect(onChange).not.toHaveBeenCalled()
  })

  it('stops adding once maxItems is reached', () => {
    const { onChange } = renderInput({ value: ['anna@example.com'], maxItems: 1 })
    const input = screen.getByRole('combobox')

    fireEvent.change(input, { target: { value: 'marco@example.com' } })
    fireEvent.keyDown(input, { key: 'Enter' })

    expect(onChange).not.toHaveBeenCalled()
  })

  it('forwards id/aria-describedby so an external <label>/error message associate with the field', () => {
    render(
      <>
        <label htmlFor="to-field">To</label>
        <EmailRecipientsInput
          id="to-field"
          value={[]}
          onChange={vi.fn()}
          removeLabel="Remove"
          aria-describedby="to-field-error"
        />
      </>,
    )
    const input = screen.getByRole('combobox', { name: 'To' })
    expect(input).toHaveAttribute('aria-describedby', 'to-field-error')
  })
})
