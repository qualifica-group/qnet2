import { useState } from 'react'
import { describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import {
  SearchableMultiSelect,
  type SearchableMultiSelectLabels,
  type SearchableMultiSelectOption,
} from '@/components/ui/searchable-multi-select'

const labels: SearchableMultiSelectLabels = {
  placeholder: 'Pick…',
  allSelected: 'All',
  searchPlaceholder: 'Search…',
  selectAll: 'Select all',
  noMatch: 'No match',
  clear: 'Clear',
  count: (selected, total) => `${selected} of ${total}`,
}

/** A group header (`ISO`) over two leaves, plus a leaf outside the group. */
const OPTIONS: SearchableMultiSelectOption[] = [
  { value: 'iso', label: 'ISO', selectable: false },
  { value: '9001', label: 'ISO 9001', parentValue: 'iso' },
  { value: '14001', label: 'ISO 14001', parentValue: 'iso' },
  { value: 'gdpr', label: 'GDPR' },
]

function Harness({ initial = [] }: { initial?: string[] }) {
  const [value, setValue] = useState<string[]>(initial)
  return (
    <>
      <SearchableMultiSelect options={OPTIONS} value={value} onChange={setValue} labels={labels} aria-label="Categories" />
      <output data-testid="value">{value.join(',')}</output>
    </>
  )
}

function open() {
  fireEvent.click(screen.getByRole('button', { name: 'Categories' }))
}

describe('SearchableMultiSelect — group headers (spec 0416)', () => {
  it('a click on a group header picks its whole subtree, never the header itself', () => {
    render(<Harness />)
    open()

    fireEvent.click(screen.getByRole('checkbox', { name: 'ISO' }))

    expect(screen.getByTestId('value')).toHaveTextContent('9001,14001')
    expect(screen.getByTestId('value')).not.toHaveTextContent('iso')
  })

  it('a second click on a fully picked header drops its subtree', () => {
    render(<Harness initial={['9001', '14001', 'gdpr']} />)
    open()

    fireEvent.click(screen.getByRole('checkbox', { name: 'ISO' }))

    expect(screen.getByTestId('value')).toHaveTextContent(/^gdpr$/)
  })

  it('shows a header as partial while only part of its subtree is picked', () => {
    render(<Harness initial={['9001']} />)
    open()

    expect(screen.getByRole('checkbox', { name: 'ISO' })).toHaveAttribute('data-state', 'indeterminate')
  })

  it('"Select all" and the counter only count the selectable options', () => {
    render(<Harness />)
    open()

    fireEvent.click(screen.getByRole('checkbox', { name: 'Select all' }))

    expect(screen.getByTestId('value')).toHaveTextContent('9001,14001,gdpr')
    expect(screen.getByText('3 of 3')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Categories' })).toHaveTextContent('All')
  })

  it('names a lone pick in the trigger rather than "all"', () => {
    render(
      <SearchableMultiSelect
        options={[{ value: 'only', label: 'Only one' }]}
        value={['only']}
        onChange={() => undefined}
        labels={labels}
        aria-label="Categories"
      />,
    )

    expect(screen.getByRole('button', { name: 'Categories' })).toHaveTextContent('Only one')
  })
})
