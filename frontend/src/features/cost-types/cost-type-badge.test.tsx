import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { CostTypeBadge } from '@/features/cost-types/cost-type-badge'
import { costTypeOptionRenderer } from '@/features/cost-types/cost-type-option-renderer'
import { costTypeColumnRenderers } from '@/features/cost-types/column-renderers'
import { swatchClassFor } from '@/features/custom-fields/badge-color-tokens'

/** Spec 0249 AC-007: the cost type reads as a badge in its own color wherever it appears. */

function expectDotOf(container: HTMLElement, token: string) {
  const dot = container.querySelector('[aria-hidden="true"]')
  expect(dot).not.toBeNull()
  for (const className of (swatchClassFor(token) ?? '').split(' ')) {
    expect(dot).toHaveClass(className)
  }
}

describe('CostTypeBadge', () => {
  it('renders the name with the dot of its color token', () => {
    const { container } = render(<CostTypeBadge name="Fitti" color="emerald" />)

    expect(screen.getByText('Fitti')).toBeInTheDocument()
    expectDotOf(container, 'emerald')
  })

  it('falls back to the plain neutral badge without a color', () => {
    const { container } = render(<CostTypeBadge name="Fitti" />)

    expect(screen.getByText('Fitti')).toBeInTheDocument()
    expect(container.querySelector('[aria-hidden="true"]')).toBeNull()
  })
})

describe('costTypeOptionRenderer', () => {
  it('projects the for-select item meta.color onto the badge', () => {
    const { container } = render(<>{costTypeOptionRenderer({ id: 3, label: 'Services', meta: { color: 'blue', nature: 'direct' } } as never)}</>)

    expect(screen.getByText('Services')).toBeInTheDocument()
    expectDotOf(container, 'blue')
  })
})

describe('cost types grid renderers', () => {
  it('renders the name as a badge in the row color', () => {
    const params = { value: 'Utenze', data: { color: 'amber' } } as unknown as ICellRendererParams
    const { container } = render(<>{costTypeColumnRenderers.name(params)}</>)

    expect(screen.getByText('Utenze')).toBeInTheDocument()
    expectDotOf(container, 'amber')
  })
})
