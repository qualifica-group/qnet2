import { beforeAll, describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { productTypologyColumnRenderers } from '@/features/product-typologies/column-renderers'
import type { ICellRendererParams } from 'ag-grid-community'

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('product typologies table - color column (spec 0204 AC-004)', () => {
  it('renders the token as a badge in that colour, named after it', () => {
    const render_ = productTypologyColumnRenderers.color
    render(<>{render_({ value: 'amber' } as ICellRendererParams)}</>)

    expect(screen.getByText('Amber')).toHaveClass('bg-amber-100')
  })
})
