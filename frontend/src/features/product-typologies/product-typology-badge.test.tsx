import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { ProductTypologyBadge } from '@/features/product-typologies/product-typology-badge'

describe('ProductTypologyBadge (spec 0204 D-5)', () => {
  it('paints the badge in the colour of the token', () => {
    render(<ProductTypologyBadge name="Ente" color="violet" />)

    expect(screen.getByText('Ente')).toHaveClass('bg-violet-100', 'text-violet-700')
  })

  it('falls back to a neutral filled badge without a known colour', () => {
    render(<ProductTypologyBadge name="Ente" />)

    expect(screen.getByText('Ente')).toHaveClass('bg-muted')
  })
})
