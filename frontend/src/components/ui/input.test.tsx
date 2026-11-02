import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { Input } from '@/components/ui/input'

describe('Input four-digit year ceiling', () => {
  it.each([
    ['date', '9999-12-31'],
    ['datetime-local', '9999-12-31T23:59'],
    ['month', '9999-12'],
  ])('caps a %s input at a 4-digit year', (type, max) => {
    render(<Input type={type} aria-label="when" />)

    expect(screen.getByLabelText('when')).toHaveAttribute('max', max)
  })

  it('keeps an explicit max', () => {
    render(<Input type="date" max="2026-10-29" aria-label="when" />)

    expect(screen.getByLabelText('when')).toHaveAttribute('max', '2026-10-29')
  })

  it('sets no max on non-date inputs', () => {
    render(<Input type="text" aria-label="name" />)

    expect(screen.getByLabelText('name')).not.toHaveAttribute('max')
  })
})
