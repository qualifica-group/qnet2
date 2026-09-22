import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { SearchableSelect, type SearchableSelectLabels } from '@/components/ui/searchable-select'

const labels: SearchableSelectLabels = {
  placeholder: 'Select a category…',
  searchPlaceholder: 'Search categories…',
  empty: 'No categories found.',
  noMatch: 'No category matches.',
  error: 'Unable to load categories.',
  retry: 'Retry',
}

const options = [
  { id: 1, name: 'Electronics' },
  { id: 2, name: 'Laptops' },
]

function renderSelect(props: Partial<Parameters<typeof SearchableSelect>[0]> = {}) {
  const onChange = vi.fn()
  render(
    <SearchableSelect value={null} onChange={onChange} options={options} labels={labels} {...props} />,
  )
  return { onChange }
}

describe('SearchableSelect', () => {
  it('selecting an option calls onChange and closes the popup', () => {
    const { onChange } = renderSelect()
    fireEvent.click(screen.getByRole('combobox'))
    fireEvent.click(screen.getByRole('option', { name: 'Laptops' }))
    expect(onChange).toHaveBeenCalledWith(2)
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('renders a clear button only with onClear and a selection, and clearing never opens the popup', () => {
    const onClear = vi.fn()
    const { rerender } = render(
      <SearchableSelect value={null} onChange={vi.fn()} onClear={onClear} options={options} labels={{ ...labels, clearLabel: 'Clear' }} />,
    )
    expect(screen.queryByRole('button', { name: /Clear/ })).not.toBeInTheDocument()

    rerender(
      <SearchableSelect value={2} onChange={vi.fn()} onClear={onClear} options={options} labels={{ ...labels, clearLabel: 'Clear' }} />,
    )
    fireEvent.click(screen.getByRole('button', { name: 'Clear Laptops' }))

    expect(onClear).toHaveBeenCalledTimes(1)
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument()
  })

  it('shows no clear button without onClear', () => {
    renderSelect({ value: 2 })
    expect(screen.queryByRole('button', { name: /Laptops/ })).not.toBeInTheDocument()
  })

  it('forwards id to the trigger so an external <label htmlFor> associates with it', () => {
    renderSelect({ id: 'parent-category-field' })
    expect(screen.getByRole('combobox')).toHaveAttribute('id', 'parent-category-field')
  })

  it('is reachable by its accessible name once linked to a <label>, mirroring FormControl (Radix Slot)', () => {
    render(
      <>
        <label htmlFor="parent-category-field">Parent category</label>
        <SearchableSelect
          id="parent-category-field"
          value={null}
          onChange={vi.fn()}
          options={options}
          labels={labels}
        />
      </>,
    )
    expect(screen.getByRole('combobox', { name: 'Parent category' })).toBeInTheDocument()
  })

  it('forwards aria-describedby and aria-invalid, exposing the error triad on the trigger', () => {
    render(
      <>
        <label htmlFor="parent-category-field">Parent category</label>
        <SearchableSelect
          id="parent-category-field"
          aria-describedby="parent-category-error"
          aria-invalid
          value={null}
          onChange={vi.fn()}
          options={options}
          labels={labels}
        />
        <span id="parent-category-error" role="alert">
          Parent category is required.
        </span>
      </>,
    )
    const trigger = screen.getByRole('combobox', { name: 'Parent category' })
    expect(trigger).toHaveAttribute('aria-invalid', 'true')
    expect(trigger).toHaveAttribute('aria-describedby', 'parent-category-error')
    expect(screen.getByRole('alert')).toHaveTextContent('Parent category is required.')
  })
})
