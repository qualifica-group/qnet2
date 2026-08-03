import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'

import { Checkbox } from '@/components/ui/checkbox'

describe('Checkbox', () => {
  it('exposes the unchecked state', () => {
    render(<Checkbox aria-label="Select all" checked={false} />)

    const checkbox = screen.getByRole('checkbox', { name: 'Select all' })

    expect(checkbox).toHaveAttribute('aria-checked', 'false')
    expect(checkbox).toHaveAttribute('data-state', 'unchecked')
  })

  it('exposes the checked state and renders the check icon', () => {
    render(<Checkbox aria-label="Select all" checked />)

    const checkbox = screen.getByRole('checkbox', { name: 'Select all' })

    expect(checkbox).toHaveAttribute('aria-checked', 'true')
    expect(checkbox).toHaveAttribute('data-state', 'checked')
    expect(checkbox.querySelector('svg.lucide-check')).toBeInTheDocument()
  })

  it('exposes the indeterminate state as aria-checked="mixed" and renders the dash icon', () => {
    render(<Checkbox aria-label="Select all" checked="indeterminate" />)

    const checkbox = screen.getByRole('checkbox', { name: 'Select all' })

    expect(checkbox).toHaveAttribute('aria-checked', 'mixed')
    expect(checkbox).toHaveAttribute('data-state', 'indeterminate')
    expect(checkbox.querySelector('svg.lucide-minus')).toBeInTheDocument()
  })
})
