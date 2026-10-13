import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import {
  purchaseRequestColumnRenderers,
  purchaseRequestLineColumnRenderers,
} from '@/features/purchase-requests/column-renderers'

function renderPriority(renderers: typeof purchaseRequestColumnRenderers, value: unknown) {
  const renderer = renderers.priority
  if (!renderer) {
    throw new Error('Missing priority renderer')
  }
  return render(<>{renderer({ value, data: {} } as unknown as ICellRendererParams)}</>)
}

describe('RDA priority column (spec 0247 AC-013)', () => {
  it.each([
    ['RDA grid', purchaseRequestColumnRenderers],
    ['RDA lines grid', purchaseRequestLineColumnRenderers],
  ])('draws the configured priority as a colored badge in the %s', (_label, renderers) => {
    renderPriority(renderers, { id: 4, name: 'Urgent', color: 'orange', icon: null })

    const badge = screen.getByText('Urgent').closest('[data-slot="badge"]')
    expect(badge).not.toBeNull()
    expect(badge?.className).toMatch(/orange/)
  })

  it('renders a placeholder when the RDA has no priority', () => {
    renderPriority(purchaseRequestColumnRenderers, null)

    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
