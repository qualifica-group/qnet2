import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { SegmentedControl } from '@/components/ui/segmented-control'

const OPTIONS = [
  { value: 'a', label: 'Alpha' },
  { value: 'b', label: 'Beta' },
  { value: 'c', label: 'Gamma' },
] as const

describe('SegmentedControl', () => {
  it('marks the picked option and keeps the single tab stop on it', () => {
    render(<SegmentedControl aria-label="Mode" value="b" options={OPTIONS} onValueChange={vi.fn()} />)

    expect(screen.getByRole('radiogroup', { name: 'Mode' })).toBeInTheDocument()
    expect(screen.getByRole('radio', { name: 'Beta' })).toBeChecked()
    expect(screen.getByRole('radio', { name: 'Beta' })).toHaveAttribute('tabindex', '0')
    expect(screen.getByRole('radio', { name: 'Alpha' })).toHaveAttribute('tabindex', '-1')
  })

  it('selects on click', () => {
    const onValueChange = vi.fn()
    render(<SegmentedControl aria-label="Mode" value="a" options={OPTIONS} onValueChange={onValueChange} />)

    fireEvent.click(screen.getByRole('radio', { name: 'Gamma' }))

    expect(onValueChange).toHaveBeenCalledWith('c')
  })

  it('moves the selection with the arrow keys, wrapping around', () => {
    const onValueChange = vi.fn()
    render(<SegmentedControl aria-label="Mode" value="c" options={OPTIONS} onValueChange={onValueChange} />)

    fireEvent.keyDown(screen.getByRole('radio', { name: 'Gamma' }), { key: 'ArrowRight' })
    expect(onValueChange).toHaveBeenLastCalledWith('a')

    fireEvent.keyDown(screen.getByRole('radio', { name: 'Gamma' }), { key: 'ArrowLeft' })
    expect(onValueChange).toHaveBeenLastCalledWith('b')
  })

  it('offers the first option as tab stop when nothing is picked, and ignores input while disabled', () => {
    const onValueChange = vi.fn()
    render(<SegmentedControl aria-label="Mode" value={null} options={OPTIONS} onValueChange={onValueChange} disabled />)

    expect(screen.getByRole('radio', { name: 'Alpha' })).toHaveAttribute('tabindex', '0')
    fireEvent.keyDown(screen.getByRole('radio', { name: 'Alpha' }), { key: 'ArrowRight' })
    expect(onValueChange).not.toHaveBeenCalled()
  })
})
