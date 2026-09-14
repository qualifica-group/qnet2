import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { TimeEntriesSortingDialog } from '@/features/time-entries/days/time-entries-sorting-dialog'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('TimeEntriesSortingDialog (D-13)', () => {
  it('shows no badge when the sort is the default (date/asc)', () => {
    render(<TimeEntriesSortingDialog onChange={vi.fn()} sortBy="date" sortDirection="asc" />)

    expect(screen.getByRole('button', { name: /Sorting/i })).toBeInTheDocument()
    expect(screen.queryByText('1')).not.toBeInTheDocument()
  })

  it('shows the custom badge once the sort differs from the default', () => {
    render(<TimeEntriesSortingDialog onChange={vi.fn()} sortBy="target_minutes" sortDirection="desc" />)

    expect(screen.getByText('1')).toBeInTheDocument()
  })

  it('applies a sort-by change immediately, keeping the current direction', async () => {
    const onChange = vi.fn()
    render(<TimeEntriesSortingDialog onChange={onChange} sortBy="date" sortDirection="asc" />)

    fireEvent.click(screen.getByRole('button', { name: /Sorting/i }))
    const [sortByCombobox] = screen.getAllByRole('combobox')
    fireEvent.click(sortByCombobox)
    fireEvent.click(await screen.findByRole('option', { name: 'Target' }))

    expect(onChange).toHaveBeenCalledWith('target_minutes', 'asc')
  })

  it('resets to the D-13 defaults', () => {
    const onChange = vi.fn()
    render(<TimeEntriesSortingDialog onChange={onChange} sortBy="is_active" sortDirection="desc" />)

    fireEvent.click(screen.getByRole('button', { name: /Sorting/i }))
    fireEvent.click(screen.getByRole('button', { name: 'Reset sorting' }))

    expect(onChange).toHaveBeenCalledWith('date', 'asc')
  })
})
